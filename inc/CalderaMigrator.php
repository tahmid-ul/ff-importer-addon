<?php


use FluentForm\App\Modules\Form\Form;
use FluentForm\Framework\Helpers\ArrayHelper;

class CalderaMigrator extends BaseMigrator
{
    public function __construct()
    {
        $this->key = 'caldera';
        $this->title = 'Caldera Forms';
        $this->shortcode = 'caldera_form';
        $this->hasStep = false;
        parent::__construct();
    }

    /**
     * @return bool
     */
    public function exist()
    {
        return defined('CFCORE_VER');
    }

    /**
     * @return array
     */
    public function getForms()
    {
        $forms = [];
        $items = Caldera_Forms_Forms::get_forms();
        foreach ($items as $item) {
            $forms[] = Caldera_Forms_Forms::get_form($item);
        }
        return $forms;
    }

    /**
     * @param $form
     * @return array
     */
    public function getFields($form)
    {
        $fluentFields = [];
        $fields = Caldera_Forms_Forms::get_fields($form);

        foreach ($fields as $name => $field) {
            $field = (array)$field;
            list($type, $args) = $this->formatFieldData($field , $form);
            if ($value = $this->getFluentClassicField($type, $args)) {
                $fluentFields[$field['ID']] = $value;
            }
        }

        
        $returnData = [
            'fields' => $this->getContainer($form, $fluentFields),
            'submitButton' => $this->submitBtn
        ];

        if ($this->hasStep && defined('FLUENTFORMPRO')) {
            $returnData['stepsWrapper'] = $this->getStepWrapper();
        }
        
        return $returnData;
    } 

    private function formatFieldData($field ,$form)
    {
        if (ArrayHelper::get($field, 'config.type_override')) {
            $field['type'] = $field['config']['type_override'];
        }

        $args = [
            'uniqElKey' => $field['ID'],
            'index' => $field['ID'], // get the order id from order array
            'required' => isset($field['required']) ? true : false,
            'label' => $field['label'],
            'label_placement' => $this->getLabelPlacement($field),
            'name' => $field['slug'],
            'placeholder' => ArrayHelper::get($field, 'config.placeholder'),
            'class' => $field['config']['custom_class'],
            'value' => $this->dynamicShortcodeConverter(ArrayHelper::get($field, 'config.default')),
            'help_message' => ArrayHelper::get($field, 'caption'),
        ];

        $type = ArrayHelper::get($this->fieldTypes(), $field['type'], '');

            switch ($type) {
                case 'input_text':
                    if (ArrayHelper::isTrue($field, 'config.masked')) {
                        $type = 'input_mask';
                        $args['temp_mask'] = 'custom';
                        $args['mask'] = str_replace('9', '0', $field['config']['mask']);//replace mask 9 with 0 for numbers
                    }
                    break;
                case 'email':
                case 'input_textarea':
                    $args['rows'] = ArrayHelper::get($field, 'config.rows');
                    break;
                case 'input_url':
                case 'color_picker':
                case 'section_break':
                case 'select':
                case 'input_radio':
                case 'input_checkbox':
                case 'dropdown':
                    $args['options'] = $this->getOptions(ArrayHelper::get($field, 'config.option', []));
                    $args['calc_value_status'] = ArrayHelper::get($field, 'config.show_values') ? true : false;
                    
                    // Toggle switch field in Caldera
                    $isBttnType = ArrayHelper::get($field, 'type') == 'toggle_switch' ? true : false;
                    if ($isBttnType) {
                        $args['layout_class'] = 'ff_list_buttons'; //for btn type radio
                    }
                    break;
                case 'multi_select':
                    $args['options'] = $this->getOptions(ArrayHelper::get($field, 'config.option', []));
                    $args['calc_value_status'] = ArrayHelper::get($field, 'config.show_values') ? true : false;
                    break;
                case 'input_date':
                    $args['format'] = Arrayhelper::get($field, 'config.format');
                    break;
                case 'input_number':
                    $args['step'] = $field['config']['step'];
                    $args['min'] = $field['config']['min'];
                    $args['max'] = $field['config']['max'];

                    // Caldera Calculation field
                    if(ArrayHelper::get($field, 'type') == 'calculation') {
                        $args['prefix'] = $field['config']['before'];
                        $args['suffix'] = $field['config']['after'];

                        if (ArrayHelper::isTrue($field, 'config.manual') || !empty(Arrayhelper::get($field, 'config.formular'))) {
                            $args['enable_calculation'] = true;
                            $args['calculation_formula'] = $this->convertFormulas($field, $form);
                        } 
                    }
                    
                    break;
                case 'rangeslider':
                    $args['step'] = $field['config']['step'];
                    $args['min'] = $field['config']['min'];
                    $args['max'] = $field['config']['max'];
                    break;
                case 'ratings':
                    $number = ArrayHelper::get($field, 'config.number', 5);
                    $args['options'] = array_combine(range(1, $number), range(1, $number));
                    break;
                case 'input_file':
                    $args['help_message'] = $field['caption'];
                    $args['allowed_file_types'] = $this->getFileTypes($field, 'config.allowed');
                    $args['max_size_unit'] = 'KB';
                    $args['max_file_size'] = $this->getFileSize($field);
                    $args['max_file_count'] = ArrayHelper::isTrue($field,
                        'config.multi_upload') ? 5 : 1; //limit 5 for unlimited files
                    $args['upload_btn_text'] = ArrayHelper::get($field, 'config.multi_upload_text') ?: 'File Upload';
                    break;
                case 'custom_html':
                    $args['html_codes'] = $field['config']['default'];
                    $args['container_class'] = $field['config']['custom_class'];
                    break;

                case 'gdpr_agreement':
                    $args['tnc_html'] = $field['config']['agreement'];
                    break;
                case 'button':
                    $pageLength = count(ArrayHelper::get($form, 'page_names'));
                    if ($field['config']['type'] == 'next' && $pageLength > 1) {
                        $this->hasStep = true;
                        $type = 'form_step';
                        break; //skipped prev button ,only one is required
                    } elseif ($field['config']['type'] != 'submit') {
                        break;
                    }
                   $this->submitBtn = $this->getSubmitBttn([
                        'uniqElKey' => $field['ID'],
                        'label' => $field['label'],
                        'class' => $field['config']['custom_class'],
                    ]);
                    break;
            }

            return array($type, $args);

    }

    private function getLabelPlacement($field) {
        if(ArrayHelper::get($field,'hide_label') == 1) {
            return 'hide_label';
        }
        return '';
    }

     /**
     * @param $field
     * @return int
     */
    private function getFileSize($field) {
        $fileSizeByte = ArrayHelper::get($field, 'config.max_upload', 6000);
        $fileSizeKilobyte = ceil(($fileSizeByte * 1024)/1000);

        return $fileSizeKilobyte;
    }

    /**
     * Function to convert shortcodes in numeric field calculations 
     * @param $field, $form
     * @return string
    */ 
    private function convertFormulas($calculationField, $form) {

        $calderaFormula;
        $fieldSlug = [];
        $fieldID = [];

        foreach ($form['fields'] as $field) {
            $prefixTypes = ArrayHelper::get($this->fieldPrefix(), $field['type'], '');   
            
            // FieldSlug for Manual Formula   
            $fieldSlug[$field['slug']] = '{' . $prefixTypes . '.' . $field['slug'] . '}';
            
            // FieldID for Direct Formula
            $fieldID[$field['ID']] = '{' . $prefixTypes . '.' . $field['slug'] . '}';        
        }

        // Check if Manual Formula Enabled in Caldera Otherwise get Direct Formula
        if (!empty($calculationField['config']['manual'])) {

            $calderaFormula = $calculationField['config']['manual_formula'];
            
            $refactorShortcode = str_replace("%", "", $calderaFormula);
            
            $refactoredFormula = str_replace(array_keys($fieldSlug), array_values($fieldSlug), $refactorShortcode);    

        } else if (!empty($calculationField['config']['formular'])) { 
            
            $calderaFormula = $calculationField['config']['formular'];  
            
            $refactoredFormula = str_replace(array_keys($fieldID), array_values($fieldID), $calderaFormula);

        }

        return $refactoredFormula;
    }

    /**
     * @return array
     */
    public function fieldPrefix() 
    {
        $fieldPrefix = [
            'number' => 'input',
            'hidden' => 'input',
            'range_slider' => 'input',
            'calculation' => 'input',
            'checkbox' => 'checkbox',
            'radio' => 'radio',
            'toggle_switch' => 'radio',
            'dropdown' => 'select',
            'filtered_select2' => 'select'
        ];

        return $fieldPrefix;
    }


    /**
     * Convert Caldera Shortcodes to Fluent forms dynamic shortcodes.
     * @param $msg
     * @return string
     */
    private function dynamicShortcodeConverter($msg) {

        $shortcodes = $this->dynamicShortcodes();

        $msg = str_replace(array_keys($shortcodes), array_values($shortcodes), $msg);

        return $msg;
    }

    /**
     * Get shortcode in fluentforms format
     * @return array
     */
    protected function dynamicShortcodes()
    {
        $dynamicShortcodes = [
            '{entry_id}' => '{submission.id}',
            '{entry_token}' => '',
            'user:' => 'user.',
            'user.id' => 'user.ID',
            '{post:*}' => '',
            '{request:*}' => '',
            '{get:*}' => '{get.Replace_text}',
            'embed_post:' => 'embed_post.',
            '{post_meta:*}' => '{embed_post.meta.YOUR_META_KEY}',
            'date:' => 'date.',
            '{login_url}' => '{wp.site_url}/wp-login.php',
            '{logout_url}' => '{wp.site_url}/wp-login.php?action=logout&_wpnonce=db111219f3',
            '{register_url}' => '{wp.site_url}/wp-login.php?action=register',
            '{lostpassword_url}' => '{wp.site_url}/wp-login.php?action=lostpassword',
            '{referer_url}' => '{http_referer}'
        ];

        return $dynamicShortcodes;
    }


    /**
     * @return array
     */
    public function fieldTypes()
    {
        $fieldTypes = [
            'email' => 'email',
            'text' => 'input_text',
            'hidden' => 'input_hidden',
            'textarea' => 'input_textarea',
            'paragraph' => 'input_textarea',
            'wysiwyg' => 'input_textarea',
            'url' => 'input_url',
            'color_picker' => 'color_picker',
            'phone_better' => 'phone',
            'phone' => 'phone',
            'select' => 'select',
            'dropdown' => 'select',
            'filtered_select2' => 'multi_select',
            'radio' => 'input_radio',
            'checkbox' => 'input_checkbox',
            'toggle_switch' => 'input_radio',
            'date_picker' => 'input_date',
            'date' => 'input_date',
            'range' => 'input_number',
            'number' => 'input_number',
            'calculation' => 'input_number',
            'range_slider' => 'rangeslider',
            'star_rating' => 'ratings',
            'file' => 'input_file',
            'cf2_file' => 'input_file',
            'advanced_file' => 'input_file',
            'html' => 'custom_html',
            'section_break' => 'section_break',
            'gdpr' => 'gdpr_agreement',
            'button' => 'button',
        ];
        return $fieldTypes;
    }

    /**
     * @param $options
     * @return array
     */
    public function getOptions($options)
    {
        $formattedOptions = [];
        foreach ($options as $key => $option) {
            $formattedOptions[] = [
                'label' => ArrayHelper::get($option, 'label', 'Item -' . $key),
                'value' => ArrayHelper::get($option, 'value'),
                'calc_value' => ArrayHelper::get($option, 'calc_value'),
                'id' => $key
            ];
        }
        return $formattedOptions;
    }

    /**
     * @param $form
     * @param $fluentFields
     * @return array
     */
    private function getContainer($form, $fluentFields)
    {
        $containers = [];
        if (empty($form['layout_grid']['fields'])) {
            return $fluentFields;
        }
        //set fields array map for inserting into containers
        foreach ($form['layout_grid']['fields'] as $field_id => $location) {
            if (isset($fluentFields[$field_id])) {
                $location = explode(':', $location);
                $containers[$location[0]][$location[1]]['fields'][] = $fluentFields[$field_id];
            }
        }
        $withContainer = [];
        foreach ($containers as $row => $columns) {

            $colsCount = count($columns);
            $containerConfig = [];
            if ($colsCount != 1) {
                //with container
                $containerConfig[] = [
                    'index' => $row,
                    'element' => 'container',
                    'settings' => [
                        'container_class',
                        'conditional_logics'
                    ],
                    'editor_options' => [
                        'title' => $colsCount . ' Column Container',
                        'icon_class' => $colsCount . 'dashicons dashicons-align-center'
                    ],
                    'columns' => $columns,
                    'uniqElKey' => 'col' . '_' . md5(uniqid(mt_rand(), true))
                ];
            } else {
                //without container
                $containerConfig = $columns[1]['fields'];
            }
            $withContainer[] = $containerConfig;
        }
        array_filter($withContainer);
        return (self::arrayFlat($withContainer));
    }

    /**
     * @param null $array
     * @param int $depth
     * @return array
     */
    public static function arrayFlat($array = null, $depth = 1)
    {
        $result = [];
        if (!is_array($array)) {
            $array = func_get_args();
        }
        foreach ($array as $key => $value) {
            if (is_array($value) && $depth) {
                $result = array_merge($result, self::arrayFlat($value, $depth - 1));
            } else {
                $result = array_merge($result, [$key => $value]);
            }
        }
        return $result;
    }

    /**
     * @return array
     */
    private function getStepWrapper()
    {
        return [
            'stepStart' => [
                'element' => 'step_start',
                'attributes' => [
                    'id' => '',
                    'class' => '',
                ],
                'settings' => [
                    'progress_indicator' => 'progress-bar',
                    'step_titles' => [],
                    'disable_auto_focus' => 'no',
                    'enable_auto_slider' => 'no',
                    'enable_step_data_persistency' => 'no',
                    'enable_step_page_resume' => 'no',
                ],
                'editor_options' => [
                    'title' => 'Start Paging'
                ],
            ],
            'stepEnd' => [
                'element' => 'step_end',
                'attributes' => [
                    'id' => '',
                    'class' => '',
                ],
                'settings' => [
                    'prev_btn' => [
                        'type' => 'default',
                        'text' => 'Previous',
                        'img_url' => ''
                    ]
                ],
                'editor_options' => [
                    'title' => 'End Paging'
                ],
            ]

        ];
    }

    /**
     * @param $form
     * @return array default parsed form metas
     * @throws \Exception
     */
    public function getFormMetas($form)
    {
        $formObject = new Form(wpFluentForm());
        $defaults = $formObject->getFormsDefaultSettings();
        $confirmation = wp_parse_args(
            [
                'messageToShow' => $form['success'],
                'samePageFormBehavior' => isset($form['hide_form']) ? 'hide_form' : 'reset_form',
            ], $defaults['confirmation']
        );
        $advancedValidation = [
            'status' => false,
            'type' => 'all',
            'conditions' => [
                [
                    'field' => '',
                    'operator' => '=',
                    'value' => ''
                ]
            ],
            'error_message' => '',
            'validation_type' => 'fail_on_condition_met'
        ];
        $notifications =
            [
                'sendTo' => [
                    'type' => 'email',
                    'email' => ArrayHelper::get($form, 'mailer.recipients'),
                    'field' => '',
                    'routing' => [],
                ],
                'enabled' => $form['mailer']['on_insert'] ? true : false,
                'name' => 'Admin Notification',
                'subject' => ArrayHelper::get($form, 'mailer.email_subject', 'Admin Notification'),
                'to' => ArrayHelper::get($form, 'mailer.recipients', '{wp.admin_email}'),
                'replyTo' => ArrayHelper::get($form, 'mailer.reply_to', '{wp.admin_email}'),
                'message' => str_replace('{summary}', '{all_data}', ArrayHelper::get($form, 'mailer.email_message')),
                'fromName' => ArrayHelper::get($form, 'mailer.sender_name'),
                'fromEmail' => ArrayHelper::get($form, 'mailer.sender_email'),
                'bcc' => ArrayHelper::get($form, 'mailer.bcc_to'),
            ];
        return [
            'formSettings' => [
                'confirmation' => $confirmation,
                'restrictions' => $defaults['restrictions'],
                'layout' => $defaults['layout'],
            ],
            'advancedValidationSettings' => $advancedValidation,
            'delete_entry_on_submission' => 'no',
            'notifications' => [$notifications]
        ];
    }

    /**
     * @param $form
     * @return mixed
     */
    protected function getFormId($form)
    {
        return $form['ID'];
    }

    /**
     * @param $form
     * @return mixed
     */
    protected function getFormName($form)
    {
        return $form['name'];
    }

}
