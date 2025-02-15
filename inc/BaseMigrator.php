<?php


use FluentForm\Framework\Helpers\ArrayHelper;

abstract class BaseMigrator
{
    public $key;
    public $title;
    public $shortcode;
    public $submitBtn;

    public function __construct()
    {

        add_action('ff_import_forms_' . $this->key, [$this, 'import_forms']);
    }

    public static function init()
    {
        new CalderaMigrator();
        new NinjaFormsMigrator();
        new GravityFormsMigrator();

        $type = sanitize_text_field($_REQUEST['type']);
        $migrators = ['caldera', 'ninja_forms','gravityform'];
        if (!$type || !in_array($type, $migrators)) {
            wp_send_json_error([
                'message' => __("Error. Unsupported migration", 'fluentform')
            ], 422);
            die();
        }
        do_action('ff_import_forms_' . $type);
    }

    public function import_forms($data)
    {
        // check if installed
        if (!$this->exist()) {
            wp_send_json_error([
                'message' => sprintf(__('%s is not installed.', 'fluentforms'), $this->title),
            ]);
        }

        $imported = 0;
        $failed = [];
        $refs = []; //todo
        $forms = $this->getForms();

        if (!$forms) {
            wp_send_json_error([
                'message' => __('No forms found!', 'fluentforms'),
            ]);
        }

        if ($forms) {

            $insertedForms = [];
            if ($forms && is_array($forms)) {
                foreach ($forms as $formItem) {
                    // First of all make the form object.
                    $formFields = json_encode([]);
                    if ($fields = $this->getFields($formItem)) {
                        $formFields = json_encode($fields);
                    } else {
                        $failed[] = $this->getFormName($formItem);
                        continue;
                    }
                    $form = [
                        'title' => $this->getFormName($formItem),
                        'form_fields' => $formFields,
                        'status' => 'published',
                        'has_payment' => 0,
                        'type' => 'form',
                        'created_by' => get_current_user_id()
                    ];
                    $form['conditions'] = ''; //todo
                    $form['appearance_settings'] = ''; //todo

                    // Insert the form to the DB.
                    $formId = wpFluent()->table('fluentform_forms')->insert($form);
                    $insertedForms[$formId] = [
                        'title' => $form['title'],
                        'edit_url' => admin_url('admin.php?page=fluent_forms&route=editor&form_id=' . $formId)
                    ];
                    //get metas
                    $metas = $this->getFormMetas($formItem);
                    $this->insertMetas($metas, $formId);
                    do_action('fluentform_form_imported', $formId);
                }
                $msg = '';
                if (count($failed) > 0) {
                    $msg = "These forms was not imported for invalid data : " . implode(', ',$failed);
                }
                if (count($insertedForms) > 0) {
                    wp_send_json([
                        'message' => "Your forms has been successfully imported. " . $msg,
                        'inserted_forms' => $insertedForms
                    ], 200);
                    return;
                }
                wp_send_json_error([
                    'message' => "Import failed fo this forms for invalid data. " . $msg,
                ], 200);

            }
        }

        wp_send_json([
            'message' => __('Export error, please try again.', 'fluentform')
        ], 422);
    }

    abstract protected function getForms();

    abstract protected function getFields($form);

    abstract protected function getFormName($form);

    abstract protected function getFormMetas($form);

    public function getFluentClassicField($field, $args = [])
    {
        if(!$field){
            return;
        }
        $defaults = [
            'index' => '',
            'uniqElKey' => '',
            'required' => false,
            'label' => '',
            'label_placement' => '',
            'admin_field_label' => '',
            'name' => '',
            'help_message' => '',
            'placeholder' => '',
            'fields' => [],
            'value' => '',
            'default' => '',
            'maxlength' => '',
            'options' => [],
            'class' => '',
            'format' => '',
            'validation_rules' => [],
            'conditional_logics' => [],
            'enable_image_input' => false,
            'calc_value_status' => false,
            'dynamic_default_value' => '',
            'container_class' => '',
            'id' => '',
            'number_step' => '',
            'step' => '1',
            'min' => '0',
            'max' => '10',
            'mask' => '',
            'temp_mask' => '',
            'enable_select_2' => 'no',
            'is_button_type' => '',
            'max_file_count' => '',
            'max_file_size' => '',
            'upload_btn_text' => '',
            'allowed_file_types' => '',
            'max_size_unit' => '',
            'html_codes' => '',
            'section_break_desc' => '',
            'tnc_html' => '',
            'prefix' => '',
            'suffix' => '',
            'layout_class' => '',
            'input_name_args'=>'',
            'is_time_enabled'=>'',
            'address_args'=>'',
            'rows' => '',
            'cols' => '',
            'enable_calculation' => false,
            'calculation_formula' => ''
        ];

        $args = wp_parse_args($args, $defaults);
        return ArrayHelper::get(self::defaultFieldConfig($args), $field);
    }

    public static function defaultFieldConfig($args)
    {
        $defaultElements = [
            'input_name' => [
                'index' => $args['index'],
                'element' => 'input_name',
                'attributes' => [
                    'name' => $args['name'],
                    'data-type' => 'name-element'
                ],
                'settings' => [
                    'container_class' => '',
                    'admin_field_label' => 'Name',
                    'conditional_logics' => [],
                    'label_placement' => 'top'
                ],
                'fields' => [
                    'first_name' => [
                        'element' => 'input_text',
                        'attributes' => [
                            'type' => 'text',
                            'name' => ArrayHelper::get($args,'input_name_args.first_name.name') ,
                            'value' => '',
                            'id' => '',
                            'class' => '',
                            'placeholder' => __('First Name', 'fluentform'),
                            'maxlength' => '',
                        ],
                        'settings' => [
                            'container_class' => '',
                            'label' =>  ArrayHelper::get($args,'input_name_args.first_name.label') ,
                            'help_message' => '',
                            'visible' =>  ArrayHelper::get($args,'input_name_args.first_name.visible'),
                            'validation_rules' => [
                                'required' => [
                                    'value' => false,
                                    'message' => __('This field is required', 'fluentform'),
                                ],
                            ],
                            'conditional_logics' => [],
                        ],
                        'editor_options' => [
                            'template' => 'inputText'
                        ],
                    ],
                    'middle_name' => [
                        'element' => 'input_text',
                        'attributes' => [
                            'type' => 'text',
                            'name' =>  ArrayHelper::get($args,'input_name_args.middle_name.name') ,
                            'value' => '',
                            'id' => '',
                            'class' => '',
                            'placeholder' => __('Middle Name', 'fluentform'),
                            'required' => false,
                            'maxlength' => '',
                        ],
                        'settings' => [
                            'container_class' => '',
                            'label' =>  ArrayHelper::get($args,'input_name_args.middle_name.label'),
                            'help_message' => '',
                            'error_message' => '',
                            'visible' => ArrayHelper::get($args,'input_name_args.middle_name.visible'),
                            'validation_rules' => [
                                'required' => [
                                    'value' => false,
                                    'message' => __('This field is required', 'fluentform'),
                                ],
                            ],
                            'conditional_logics' => [],
                        ],
                        'editor_options' => [
                            'template' => 'inputText'
                        ],
                    ],
                    'last_name' => [
                        'element' => 'input_text',
                        'attributes' => [
                            'type' => 'text',
                            'name' =>  ArrayHelper::get($args,'input_name_args.last_name.name'),
                            'value' => '',
                            'id' => '',
                            'class' => '',
                            'placeholder' => __('Last Name', 'fluentform'),
                            'required' => false,
                            'maxlength' => '',
                        ],
                        'settings' => [
                            'container_class' => '',
                            'label' =>  ArrayHelper::get($args,'input_name_args.last_name.label'),
                            'help_message' => '',
                            'error_message' => '',
                            'visible' =>  ArrayHelper::get($args,'input_name_args.last_name.visible'),
                            'validation_rules' => [
                                'required' => [
                                    'value' => false,
                                    'message' => __('This field is required', 'fluentform'),
                                ],
                            ],
                            'conditional_logics' => [],
                        ],
                        'editor_options' => [
                            'template' => 'inputText'
                        ],
                    ],
                ],
                'editor_options' => [
                    'title' => 'Name Fields',
                    'element' => 'name-fields',
                    'icon_class' => 'ff-edit-name',
                    'template' => 'nameFields'
                ],
            ],
            'input_text' => [
                'index' => $args['index'],
                'element' => 'input_text',
                'attributes' => [
                    'type' => 'text',
                    'name' => $args['name'],
                    'value' => $args['value'],
                    'class' => $args['class'],
                    'id' => $args['id'],
                    'placeholder' => $args['placeholder'],
                    'maxlength' => $args['maxlength'],
                ],
                'settings' => [
                    'container_class' => $args['container_class'],
                    'label' => $args['label'],
                    'label_placement' => $args['label_placement'],
                    'admin_field_label' => $args['admin_field_label'],
                    'help_message' => $args['help_message'],
                    'conditional_logics' => [],
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => 'This field is required'
                        ]
                    ],
                    'is_unique' => 'no',
                    'unique_validation_message' => 'This value need to be unique.'

                ],
                'editor_options' => [
                    'title' => 'Simple Text',
                    'icon_class' => 'ff-edit-text',
                    'template' => 'inputText',
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'input_hidden' => [
                'index' => $args['index'],
                'element' => 'input_hidden',
                'attributes' => [
                    'type' => 'hidden',
                    'name' => $args['name'],
                    'value' => $args['value'],
                ],
                'settings' => [
                    'admin_field_label' => $args['admin_field_label'],
                ],
                'editor_options' => [
                    'title' => __('Hidden Field', 'fluentform'),
                    'icon_class' => 'ff-edit-hidden-field',
                    'template' => 'inputHidden',
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'color_picker' => [
                'index' => 15,
                'element' => 'color_picker',
                'attributes' => [
                    'type' => 'text',
                    'name' => $args['name'],
                    'value' => $args['value'],
                    'class' => $args['class'],
                    'id' => $args['id'],
                    'placeholder' => $args['placeholder'],
                ],
                'settings' => [
                    'container_class' => $args['container_class'],
                    'label' => $args['label'],
                    'label_placement' => $args['label_placement'],
                    'admin_field_label' => $args['admin_field_label'],
                    'help_message' => $args['help_message'],
                    'conditional_logics' => [],
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => 'This field is required'
                        ]
                    ],
                ],
                'editor_options' => [
                    'title' => 'Color Picker',
                    'icon_class' => 'ff-edit-tint',
                    'template' => 'inputText'
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'input_url' => [
                'index' => $args['index'],
                'element' => 'input_url',
                'attributes' => [
                    'type' => 'url',
                    'name' => $args['name'],
                    'value' => $args['value'],
                    'class' => $args['class'],
                    'id' => $args['id'],
                    'placeholder' => $args['placeholder'],
                ],
                'settings' => [
                    'container_class' => '',
                    'label' => $args['label'],
                    'label_placement' => $args['label_placement'],
                    'admin_field_label' => $args['admin_field_label'],
                    'help_message' => $args['help_message'],
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => 'This field is required'
                        ],
                        'url' => [
                            'value' => true,
                            'message' => __('This field must contain a valid url', 'fluentform'),
                        ],
                    ],
                    'conditional_logics' => [],

                ],
                'editor_options' => [
                    'title' => __('Website URL', 'fluentform'),
                    'icon_class' => 'ff-edit-website-url',
                    'template' => 'inputText'
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'email' => [
                'index' => $args['index'],
                'element' => 'input_email',
                'attributes' => [
                    'type' => 'email',
                    'name' => $args['name'],
                    'value' => $args['value'],
                    'class' => $args['class'],
                    'id' => '',
                    'placeholder' => $args['placeholder'],
                ],
                'settings' => [
                    'container_class' => '',
                    'label' => $args['label'],
                    'label_placement' => $args['label_placement'],
                    'admin_field_label' => $args['admin_field_label'],
                    'help_message' => $args['help_message'],
                    'conditional_logics' => [],
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => 'This field is required',
                        ],
                        'email' => [
                            'value' => 1,
                            'message' => 'This field must contain a valid email'
                        ]
                    ],
                    'is_unique' => 'no',
                    'unique_validation_message' => 'This value need to be unique.'

                ],
                'editor_options' => [
                    'title' => 'Email Address',
                    'icon_class' => 'ff-edit-email',
                    'template' => 'inputText',
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'input_textarea' => [
                'index' => $args['index'],
                'element' => 'textarea',
                'attributes' => [
                    'name' => $args['name'],
                    'class' => $args['class'],
                    'id' => '',
                    'value' => $args['value'],
                    'placeholder' => $args['placeholder'],
                    'rows' => $args['rows'],
                    'cols' => 2,
                    'maxlength' => $args['maxlength'],
                ],
                'settings' => [
                    'container_class' => '',
                    'label' => $args['label'],
                    'label_placement' => $args['label_placement'],
                    'admin_field_label' => $args['admin_field_label'],
                    'help_message' => $args['help_message'],
                    'conditional_logics' => [],
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => 'This field is required'
                        ],
                    ],
                ],
                'editor_options' => [
                    'title' => 'Text Area',
                    'icon_class' => 'ff-edit-textarea',
                    'template' => 'inputTextarea',
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'select' => [
                'index' => $args['index'],
                'element' => 'select',
                'attributes' => [
                    'name' => $args['name'],
                    'value' => $args['value'],
                    'class' => $args['class'],
                    'id' => '',
                ],
                'settings' => [
                    'container_class' => '',
                    'label_placement' => $args['label_placement'],
                    'admin_field_label' => $args['admin_field_label'],
                    'label' => $args['label'],
                    'help_message' => $args['help_message'],
                    'placeholder' => $args['placeholder'],
                    'advanced_options' => $args['options'],
                    'calc_value_status' => $args['calc_value_status'],
                    'enable_select_2' => $args['enable_select_2'],
                    'enable_image_input' => false,
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => 'This field is required'
                        ]
                    ],
                    'randomize_options' => 'no',
                    'conditional_logics' => [],
                ],
                'editor_options' => [
                    'title' => 'Dropdown',
                    'icon_class' => 'ff-edit-dropdown',
                    'element' => 'select',
                    'template' => 'select',
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'multi_select' => [
                'index' => $args['index'],
                'element' => 'select',
                'attributes' => [
                    'name' => $args['name'],
                    'value' => $args['value'],
                    'id' => $args['id'],
                    'class' => $args['class'],
                    'placeholder' => $args['placeholder'],
                    'multiple' => true,
                ],
                'settings' => [
                    'dynamic_default_value' => $args['dynamic_default_value'],
                    'help_message' => $args['help_message'],
                    'container_class' => $args['container_class'],
                    'label' => $args['label'],
                    'admin_field_label' => $args['admin_field_label'],
                    'label_placement' => $args['label_placement'],
                    'placeholder' => $args['placeholder'],
                    'max_selection' => '',
                    'advanced_options' => $args['options'],
                    'calc_value_status' => $args['calc_value_status'],
                    'enable_image_input' => false,
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => __('This field is required', 'fluentform'),
                        ],
                    ],
                    'conditional_logics' => [],
                ],
                'editor_options' => [
                    'title' => __('Multiple Choice', 'fluentform'),
                    'icon_class' => 'ff-edit-multiple-choice',
                    'element' => 'select',
                    'template' => 'select'
                ]
            ],
            'input_checkbox' => [
                'index' => $args['index'],
                'element' => 'input_checkbox',
                'attributes' => [
                    'name' => $args['name'],
                    'value' => $args['value'],
                    'class' => $args['class'],
                    'id' => '',
                    'type' => 'checkbox'
                ],
                'settings' => [
                    'container_class' => '',
                    'label_placement' => $args['label_placement'],
                    'label' => $args['label'],
                    'help_message' => $args['help_message'],
                    'advanced_options' => $args['options'],
                    'calc_value_status' => $args['calc_value_status'],
                    'enable_image_input' => $args['enable_image_input'],
                    'randomize_options' => 'no',
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => __('This field is required', 'fluentform'),
                        ],
                    ],
                    'conditional_logics' => [],
                    'layout_class' => $args['layout_class']
                ],
                'editor_options' => [
                    'title' => __('Check Box', 'fluentform'),
                    'icon_class' => 'ff-edit-checkbox-1',
                    'template' => 'inputCheckable'
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'input_radio' => [
                'index' => $args['index'],
                'element' => 'input_radio',
                'attributes' => [
                    'name' => $args['name'],
                    'value' => $args['value'],
                    'class' => $args['class'],
                    'type' => 'radio',
                ],
                'settings' => [
                    'dynamic_default_value' => $args['dynamic_default_value'],
                    'container_class' => '',
                    'admin_field_label' => $args['admin_field_label'],
                    'label_placement' => $args['label_placement'],
                    'display_type' => '',
                    'randomize_options' => 'no',
                    'label' => $args['label'],
                    'help_message' => $args['help_message'],
                    'advanced_options' => $args['options'],
                    'layout_class' => $args['layout_class'],
                    'calc_value_status' => $args['calc_value_status'],
                    'enable_image_input' => $args['enable_image_input'],
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => __('This field is required', 'fluentform'),
                        ],
                    ],
                    'conditional_logics' => [],
                ],
                'editor_options' => [
                    'title' => __('Radio Field', 'fluentform'),
                    'icon_class' => 'ff-edit-radio',
                    'element' => 'input-radio',
                    'template' => 'inputCheckable'
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'input_date' => [
                'element' => 'input_date',
                'index' => $args['index'],
                'attributes' => [
                    'name' => $args['name'],
                    'value' => '',
                    'type' => 'text',
                    'class' => $args['class'],
                    'placeholder' => $args['placeholder'],
                    'id' => '',
                ],
                'settings' => [
                    'container_class' => '',
                    'label_placement' => $args['label_placement'],
                    'admin_field_label' => $args['admin_field_label'],
                    'label' => $args['label'],
                    'help_message' => $args['help_message'],
                    'date_format' => $args['format'],
                    'is_time_enabled' => $args['is_time_enabled'],
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => 'This field is required'
                        ]
                    ],
                ],
                'editor_options' => [
                    'title' => 'Time & Date',
                    'icon_class' => 'ff-edit-date',
                    'template' => 'inputText',
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'input_mask' => [
                'index' => $args['index'],
                'element' => 'input_text',
                'attributes' => [
                    'type' => 'text',
                    'name' => $args['name'],
                    'value' => $args['value'],
                    'class' => $args['class'],
                    'id' => $args['id'],
                    'placeholder' => $args['placeholder'],
                    'data-mask' => $args['mask'],
                ],
                'settings' => [
                    'container_class' => '',
                    'label' => $args['label'],
                    'label_placement' => $args['label_placement'],
                    'admin_field_label' => $args['admin_field_label'],
                    'help_message' => $args['help_message'],
                    'prefix_label' => '',
                    'suffix_label' => '',
                    'temp_mask' => $args['temp_mask'],
                    'data-mask-reverse' => 'no',
                    'data-clear-if-not-match' => 'no',
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => __('This field is required', 'fluentform'),
                        ]
                    ],
                    'conditional_logics' => [],
                ],
                'editor_options' => [
                    'title' => __('Mask Input', 'fluentform'),
                    'icon_class' => 'ff-edit-mask',
                    'template' => 'inputText'
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'input_password' => [
                'index' => $args['index'],
                'element' => 'input_password',
                'attributes' => [
                    'type' => 'password',
                    'name' => $args['name'],
                    'value' => $args['value'],
                    'class' => $args['class'],
                    'id' => $args['id'],
                    'placeholder' => $args['placeholder'],
                ],
                'settings' => [
                    'container_class' => $args['container_class'],
                    'label' => $args['label'],
                    'label_placement' => $args['label_placement'],
                    'admin_field_label' => $args['admin_field_label'],
                    'help_message' => $args['help_message'],
                    'conditional_logics' => [],
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => 'This field is required'
                        ]
                    ],
                ],
                'editor_options' => [
                    'title' => __('Password Field', 'fluentform'),
                    'icon_class' => 'ff-edit-password',
                    'template' => 'inputText',
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'input_number' => [
                'index' => $args['index'],
                'element' => 'input_number',
                'attributes' => [
                    'type' => 'number',
                    'name' => $args['name'],
                    'value' => $args['value'],
                    'id' => '',
                    'class' => $args['class'],
                    'placeholder' => $args['placeholder']
                ],
                'settings' => [
                    'container_class' => '',
                    'label' => $args['label'],
                    'admin_field_label' => $args['admin_field_label'],
                    'label_placement' => $args['label_placement'],
                    'help_message' => $args['help_message'],
                    'number_step' => $args['step'],
                    'prefix_label' => $args['prefix'],
                    'suffix_label' => $args['suffix'],
                    'numeric_formatter' => '',
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => __('This field is required', 'fluentform'),
                        ],
                        'numeric' => [
                            'value' => true,
                            'message' => __('This field must contain numeric value', 'fluentform'),
                        ],
                        'min' => [
                            'value' => $args['min'],
                            'message' => 'Minimum value is ' . $args['min'],
                        ],
                        'max' => [
                            'value' => $args['max'],
                            'message' => 'Maximum value is ' . $args['max'],
                        ],
                    ],
                    'conditional_logics' => [],
                    'calculation_settings' => [
                        'status' => $args['enable_calculation'],
                        'formula' => $args['calculation_formula']
                    ],
                ],
                'editor_options' => [
                    'title' => __('Numeric Field', 'fluentform'),
                    'icon_class' => 'ff-edit-numeric',
                    'template' => 'inputText'
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'phone' => [
                'index' => $args['index'],
                'element' => 'phone',
                'attributes' => [
                    'name' => $args['name'],
                    'value' => $args['value'],
                    'id' => $args['id'],
                    'class' => $args['class'],
                    'placeholder' => $args['placeholder'],
                    'type' => 'tel'
                ],
                'settings' => [
                    'container_class' => '',
                    'placeholder' => '',
                    'int_tel_number' => 'no',
                    'auto_select_country' => 'no',
                    'label' => $args['label'],
                    'label_placement' => $args['label_placement'],
                    'help_message' => $args['help_message'],
                    'admin_field_label' => $args['admin_field_label'],
                    'phone_country_list' => array(
                        'active_list' => 'all',
                        'visible_list' => array(),
                        'hidden_list' => array(),
                    ),
                    'default_country' => '',
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => __('This field is required', 'fluentformpro'),
                        ],
                        'valid_phone_number' => [
                            'value' => false,
                            'message' => __('Phone number is not valid', 'fluentformpro')
                        ]
                    ],
                    'conditional_logics' => []
                ],
                'editor_options' => [
                    'title' => 'Phone Field',
                    'icon_class' => 'el-icon-phone-outline',
                    'template' => 'inputText'
                ],
                'uniqElKey' => $args['uniqElKey'],

            ],
            'input_file' => [
                'index' => $args['index'],
                'element' => 'input_file',
                'attributes' => [
                    'type' => 'file',
                    'name' => $args['name'],
                    'value' => '',
                    'id' => $args['id'],
                    'class' => $args['class'],
                ],
                'settings' => [
                    'container_class' => '',
                    'label' => $args['label'],
                    'admin_field_label' => $args['admin_field_label'],
                    'label_placement' => $args['label_placement'],
                    'btn_text' => $args['upload_btn_text'],
                    'help_message' => $args['help_message'],
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => __('This field is required', 'fluentform'),
                        ],
                        'max_file_size' => [
                            'value' => $args['max_file_size'],
                            '_valueFrom' => $args['max_size_unit'],
                            'message' => __('Maximum file size limit reached', 'fluentform')
                        ],
                        'max_file_count' => [
                            'value' => $args['max_file_count'],
                            'message' => __('Maximum upload limit reached', 'fluentform')
                        ],
                        'allowed_file_types' => [
                            'value' => $args['allowed_file_types'],
                            'message' => __('Invalid file type', 'fluentform')
                        ]
                    ],
                    'conditional_logics' => [],
                ],
                'editor_options' => [
                    'title' => __('File Upload', 'fluentform'),
                    'icon_class' => 'ff-edit-files',
                    'template' => 'inputFile'
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'custom_html' => [
                'index' => $args['index'],
                'element' => 'custom_html',
                'attributes' => [],
                'settings' => [
                    'html_codes' => $args['html_codes'],
                    'conditional_logics' => [],
                    'container_class' => ''
                ],
                'editor_options' => [
                    'title' => __('Custom HTML', 'fluentform'),
                    'icon_class' => 'ff-edit-html',
                    'template' => 'customHTML',
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'section_break' => [
                'index' => $args['index'],
                'element' => 'section_break',
                'attributes' => [
                    'id' => $args['id'],
                    'class' => $args['class'],
                ],
                'settings' => [
                    'label' => $args['label'],
                    'description' => $args['section_break_desc'],
                    'align' => 'left',
                    'conditional_logics' => [],
                ],
                'editor_options' => [
                    'title' => __('Section Break', 'fluentform'),
                    'icon_class' => 'ff-edit-section-break',
                    'template' => 'sectionBreak',
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'rangeslider' => [
                'index' => $args['index'],
                'element' => 'rangeslider',
                'attributes' => [
                    'type' => 'range',
                    'name' => $args['name'],
                    'value' => $args['value'],
                    'id' => $args['id'],
                    'class' => $args['class'],
                    'min' => $args['min'],
                    'max' => $args['max'],
                ],
                'settings' => [
                    'number_step' => $args['step'],
                    'label' => $args['label'],
                    'help_message' => $args['help_message'],
                    'label_placement' => $args['label_placement'],
                    'admin_field_label' => $args['admin_field_label'],
                    'container_class' => '',
                    'conditional_logics' => [],
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => 'This field is required'
                        ]
                    ],
                ],
                'editor_options' => [
                    'title' => 'Range Slider',
                    'icon_class' => 'dashicons dashicons-leftright',
                    'template' => 'inputSlider'
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'ratings' => [
                'index' => $args['index'],
                'element' => 'ratings',
                'attributes' => [
                    'class' => $args['class'],
                    'value' => 0,
                    'name' => $args['name'],
                ],
                'settings' => [
                    'label' => $args['label'],
                    'show_text' => 'no',
                    'help_message' => $args['help_message'],
                    'label_placement' => $args['label_placement'],
                    'admin_field_label' => $args['admin_field_label'],
                    'container_class' => '',
                    'conditional_logics' => [],
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => __('This field is required', 'fluentform'),
                        ],
                    ],
                ],
                'options' => $args['options'],
                'editor_options' => [
                    'title' => __('Ratings', 'fluentform'),
                    'icon_class' => 'ff-edit-rating',
                    'template' => 'ratings',
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'gdpr_agreement' => [
                'index' => $args['index'],
                'element' => 'gdpr_agreement',
                'attributes' => [
                    'type' => 'checkbox',
                    'name' => $args['name'],
                    'value' => false,
                    'class' => $args['class'] . ' ff_gdpr_field',
                ],
                'settings' => [
                    'label' => $args['label'],
                    'tnc_html' => $args['tnc_html'],
                    'admin_field_label' => __('GDPR Agreement', 'fluentform'),
                    'has_checkbox' => true,
                    'container_class' => '',
                    'validation_rules' => [
                        'required' => [
                            'value' => true,
                            'message' => __('This field is required', 'fluentform'),
                        ],
                    ],
                    'required_field_message' => '',
                    'conditional_logics' => [],
                ],
                'editor_options' => [
                    'title' => __('GDPR Agreement', 'fluentform'),
                    'icon_class' => 'ff-edit-gdpr',
                    'template' => 'termsCheckbox'
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'form_step' => [
                'index' => $args['index'],
                'element' => 'form_step',
                'attributes' => [
                    'id' => '',
                    'class' => $args['class'],
                ],
                'settings' => [
                    'prev_btn' => [
                        'type' => ArrayHelper::get($args,'prev_btn.type','default'),
                        'text' => ArrayHelper::get($args,'prev_btn.text','Previous') ,
                        'img_url' => ArrayHelper::get($args,'prev_btn.img_url','')
                    ],
                    'next_btn' => [
                        'type' => ArrayHelper::get($args,'next_btn.type','default'),
                        'text' => ArrayHelper::get($args,'next_btn.text','Next') ,
                        'img_url' => ArrayHelper::get($args,'next_btn.img_url','')
                    ]
                ],
                'editor_options' => [
                    'title' => 'Form Step',
                    'icon_class' => 'ff-edit-step',
                    'template' => 'formStep'
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'select_country' => [
                'index' => $args['index'],
                'element' => 'select_country',
                'attributes' => [
                    'name' => $args['name'],
                    'value' => $args['value'],
                    'id' => $args['id'],
                    'class' => $args['class'],
                    'placeholder' => $args['placeholder'],
                ],
                'settings' => [
                    'container_class' => $args['container_class'],
                    'label' => $args['label'],
                    'admin_field_label' => '',
                    'label_placement' => $args['label_placement'],
                    'help_message' => $args['help_message'],
                    'enable_select_2' => 'no',
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => __('This field is required', 'fluentform'),
                        ],
                    ],
                    'country_list' => [
                        'active_list' => 'all',
                        'visible_list' => [],
                        'hidden_list' => [],
                    ],
                    'conditional_logics' => [],
                ],
                'options' => [
                    'US' => 'United States of America',
                ],
                'editor_options' => [
                    'title' => __('Country List', 'fluentform'),
                    'element' => 'country-list',
                    'icon_class' => 'ff-edit-country',
                    'template' => 'selectCountry'
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'repeater_field' => [
                'index' => $args['index'],
                'element' => 'repeater_field',
                'attributes' => array(
                    'name' => $args['name'],
                    'data-type' => 'repeater_field'
                ),
                'fields' => $args['fields'],
                'settings' => array(
                    'label' => $args['label'],
                    'admin_field_label' => '',
                    'container_class' => '',
                    'label_placement' => '',
                    'validation_rules' => array(),
                    'conditional_logics' => array(),
                    'max_repeat_field' => ''
                ),
                'editor_options' => array(
                    'title' => 'Repeat Field',
                    'icon_class' => 'ff-edit-repeat',
                    'template' => 'fieldsRepeatSettings'
                ),
                'uniqElKey' => $args['uniqElKey'],
            ],
            'reCaptcha' => [
                'index' => $args['index'],
                'element' => 'recaptcha',
                'attributes' => ['name' => 'recaptcha'],
                'settings' => [
                    'label' => $args['label'],
                    'label_placement' => $args['label_placement'],
                    'validation_rules' => [],
                ],
                'editor_options' => [
                    'title' => __('reCaptcha', 'fluentform'),
                    'icon_class' => 'ff-edit-recaptha',
                    'why_disabled_modal' => 'recaptcha',
                    'template' => 'recaptcha',
                ],
                'uniqElKey' => $args['uniqElKey'],
            ],
            'terms_and_condition' => [
                'index' => $args['index'],
                'element' => 'terms_and_condition',
                'attributes' => [
                    'type' => 'checkbox',
                    'name' => $args['name'],
                    'value' => false,
                    'class' => $args['class'],
                ],
                'settings' => [
                    'tnc_html' => $args['tnc_html'],
                    'has_checkbox' => true,
                    'admin_field_label' => __('Terms and Conditions', 'fluentform'),
                    'container_class' => '',
                    'validation_rules' => [
                        'required' => [
                            'value' => $args['required'],
                            'message' => __('This field is required', 'fluentform'),
                        ],
                    ],
                    'conditional_logics' => [],
                ],
                'editor_options' => [
                    'title' => __('Terms & Conditions', 'fluentform'),
                    'icon_class' => 'ff-edit-terms-condition',
                    'template' => 'termsCheckbox'
                ],
            ],
            'address' => [
                'index' => $args['index'],
                'element' => 'address',
                'attributes' => [
                    'id' => '',
                    'class' => $args['class'],
                    'name' => $args['name'],
                    'data-type' => 'address-element'
                ],
                'settings' => [
                    'label' => $args['label'],
                    'admin_field_label' => 'Address',
                    'conditional_logics' => [],
                ],
                'fields' => [
                    'address_line_1' => [
                        'element' => 'input_text',
                        'attributes' => [
                            'type' => 'text',
                            'name' => ArrayHelper::get($args,'address_args.address_line_1.name'),
                            'value' => '',
                            'id' => '',
                            'class' => '',
                            'placeholder' => __('Address Line 1', 'fluentform'),
                        ],
                        'settings' => [
                            'container_class' => '',
                            'label' => ArrayHelper::get($args,'address_args.address_line_1.label'),
                            'admin_field_label' => '',
                            'help_message' => '',
                            'visible' => ArrayHelper::get($args,'address_args.address_line_1.visible'),
                            'validation_rules' => [
                                'required' => [
                                    'value' => false,
                                    'message' => __('This field is required', 'fluentform'),
                                ],
                            ],
                            'conditional_logics' => [],
                        ],
                        'editor_options' => [
                            'template' => 'inputText'
                        ],
                    ],
                    'address_line_2' => [
                        'element' => 'input_text',
                        'attributes' => [
                            'type' => 'text',
                            'name' => ArrayHelper::get($args,'address_args.address_line_2.name'),
                            'value' => '',
                            'id' => '',
                            'class' => '',
                            'placeholder' => __('Address Line 2', 'fluentform'),
                        ],
                        'settings' => [
                            'container_class' => '',
                            'label' => ArrayHelper::get($args,'address_args.address_line_2.label'),
                            'admin_field_label' => '',
                            'help_message' => '',
                            'visible' => ArrayHelper::get($args,'address_args.address_line_2.visible'),
                            'validation_rules' => [
                                'required' => [
                                    'value' => false,
                                    'message' => __('This field is required', 'fluentform'),
                                ],
                            ],
                            'conditional_logics' => [],
                        ],
                        'editor_options' => [
                            'template' => 'inputText'
                        ],
                    ],
                    'city' => [
                        'element' => 'input_text',
                        'attributes' => [
                            'type' => 'text',
                            'name' => ArrayHelper::get($args,'address_args.city.name'),
                            'value' => '',
                            'id' => '',
                            'class' => '',
                            'placeholder' => __('City', 'fluentform'),
                        ],
                        'settings' => [
                            'container_class' => '',
                            'label' => ArrayHelper::get($args,'address_args.city.label'),
                            'admin_field_label' => '',
                            'help_message' => '',
                            'error_message' => '',
                            'visible' => ArrayHelper::get($args,'address_args.city.visible'),
                            'validation_rules' => [
                                'required' => [
                                    'value' => false,
                                    'message' => __('This field is required', 'fluentform'),
                                ],
                            ],
                            'conditional_logics' => [],
                        ],
                        'editor_options' => [
                            'template' => 'inputText'
                        ],
                    ],
                    'state' => [
                        'element' => 'input_text',
                        'attributes' => [
                            'type' => 'text',
                            'name' => ArrayHelper::get($args,'address_args.state.state'),
                            'value' => '',
                            'id' => '',
                            'class' => '',
                            'placeholder' => __('State', 'fluentform'),
                        ],
                        'settings' => [
                            'container_class' => '',
                            'label' => ArrayHelper::get($args,'address_args.state.label'),
                            'admin_field_label' => '',
                            'help_message' => '',
                            'error_message' => '',
                            'visible' => ArrayHelper::get($args,'address_args.state.visible'),
                            'validation_rules' => [
                                'required' => [
                                    'value' => false,
                                    'message' => __('This field is required', 'fluentform'),
                                ],
                            ],
                            'conditional_logics' => [],
                        ],
                        'editor_options' => [
                            'template' => 'inputText'
                        ],
                    ],
                    'zip' => [
                        'element' => 'input_text',
                        'attributes' => [
                            'type' => 'text',
                            'name' => ArrayHelper::get($args,'address_args.zip.name'),
                            'value' => '',
                            'id' => '',
                            'class' => '',
                            'placeholder' => __('Zip', 'fluentform'),
                            'required' => false,
                        ],
                        'settings' => [
                            'container_class' => '',
                            'label' => ArrayHelper::get($args,'address_args.zip.label'),
                            'admin_field_label' => '',
                            'help_message' => '',
                            'error_message' => '',
                            'visible' => ArrayHelper::get($args,'address_args.zip.visible'),
                            'validation_rules' => [
                                'required' => [
                                    'value' => false,
                                    'message' => __('This field is required', 'fluentform'),
                                ],
                            ],
                            'conditional_logics' => [],
                        ],
                        'editor_options' => [
                            'template' => 'inputText'
                        ],
                    ],
                    'country' => [
                        'element' => 'select_country',
                        'attributes' => [
                            'name' => ArrayHelper::get($args,'address_args.country.name'),
                            'value' => '',
                            'id' => '',
                            'class' => '',
                            'placeholder' => __('Select Country', 'fluentform'),
                            'required' => false,
                        ],
                        'settings' => [
                            'container_class' => '',
                            'label' => ArrayHelper::get($args,'address_args.country.label'),
                            'admin_field_label' => '',
                            'help_message' => '',
                            'error_message' => '',
                            'visible' => ArrayHelper::get($args,'address_args.country.visible'),
                            'validation_rules' => [
                                'required' => [
                                    'value' => false,
                                    'message' => __('This field is required', 'fluentform'),
                                ],
                            ],
                            'country_list' => [
                                'active_list' => 'all',
                                'visible_list' => [],
                                'hidden_list' => [],
                            ],
                            'conditional_logics' => [],
                        ],
                        'options' => [
                            'US' => 'US of America',
                            'UK' => 'United Kingdom'
                        ],
                        'editor_options' => [
                            'title' => 'Country List',
                            'element' => 'country-list',
                            'icon_class' => 'icon-text-width',
                            'template' => 'selectCountry'
                        ],
                    ],
                ],
                'editor_options' => [
                    'title' => __('Address Fields', 'fluentform'),
                    'element' => 'address-fields',
                    'icon_class' => 'ff-edit-address',
                    'template' => 'addressFields'
                ],
            ],

        ];
        if (!defined('FLUENTFORMPRO')) {
            $proElements = ['repeater_field','rangeslider','color_picker','form_step','phone','input_file'];
            foreach ($proElements as $el){
                unset($defaultElements[$el]);
            }
        }
        return $defaultElements;
    }

    public function getSubmitBttn($args)
    {
        return [
            'uniqElKey' => $args['uniqElKey'],
            'element' => 'button',
            'attributes' => [
                'type' => 'submit',
                'class' => $args['class']
            ],
            'settings' => [
                'container_class' => '',
                'align' => 'left',
                'button_style' => 'default',
                'button_size' => 'md',
                'color' => '#ffffff',
                'background_color' => '#409EFF',
                'button_ui' => [
                    'type' => ArrayHelper::get($args,'type','default'),
                    'text' => $args['label'],
                    'img_url' => ArrayHelper::get($args,'img_url','')
                ],
                'normal_styles' => [],
                'hover_styles' => [],
                'current_state' => "normal_styles"
            ],
            'editor_options' => [
                'title' => 'Submit Button',
            ],

        ];

    }

    abstract protected function getFormId($form);

    /**
     * @param $metas
     * @param $formId
     */
    protected function insertMetas($metas, $formId)
    {
        if ($metas) {
            //when multiple notifications
            if ($notifications = ArrayHelper::get($metas, 'notifications')) {
                foreach ($notifications as $notify) {
                    $settings = [
                        'form_id' => $formId,
                        'meta_key' => 'notifications',
                        'value' => json_encode($notify)
                    ];
                    wpFluent()->table('fluentform_form_meta')->insert($settings);
                }
                unset($metas['notifications']);
            }
            foreach ($metas as $metaKey => $metaData) {
                $settings = [
                    'form_id' => $formId,
                    'meta_key' => $metaKey,
                    'value' => json_encode($metaData)
                ];
                wpFluent()->table('fluentform_form_meta')->insert($settings);
            }
        }
    }

    protected function getFileTypes($field, $arg) {
        // All Supported File Types in Fluent Forms
        $allFileTypes = ["jpg|jpeg|gif|png|bmp","mp3|wav|ogg|oga|wma|mka|m4a|ra|mid|midi|mpga","avi|divx|flv|mov|ogv|mkv|mp4|m4v|divx|mpg|mpeg|mpe|video/quicktime|qt","pdf","doc|ppt|pps|xls|mdb|docx|xlsx|pptx|odt|odp|ods|odg|odc|odb|odf|rtf|txt","zip|gz|gzip|rar|7z","exe","csv"];

        $formattedTypes = explode(', ', ArrayHelper::get($field, $arg , ''));
        $fileTypeOptions = [];
        foreach ($formattedTypes as $format) {
            foreach ($allFileTypes as $fileTypes) {
                if (!empty($format) && (strpos($fileTypes, $format) !== false)) {                 
                    array_push($fileTypeOptions, $fileTypes);
                }
                
            }
        }
        
        return array_unique($fileTypeOptions);
    }


}
