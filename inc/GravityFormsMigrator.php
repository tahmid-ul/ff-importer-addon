<?php

use FluentForm\App\Modules\Form\Form;
use FluentForm\Framework\Helpers\ArrayHelper;


class GravityFormsMigrator extends BaseMigrator
{

    public function __construct()
    {
        $this->key = 'gravityform';
        $this->title = 'Gravity Forms';
        $this->shortcode = 'gravity_form';
        $this->hasStep = false;
        parent::__construct();
    }

    public function exist()
    {
        return class_exists('GFForms');
    }

    /**
     * @param $form
     * @return array
     */
    public function getFields($form)
    {
        $fluentFields = [];
        $fields = $form['fields'];

        foreach ($fields as $name => $field) {
            $field = (array)$field;
            list($type, $args) = $this->formatFieldData($field);
            if ($value = $this->getFluentClassicField($type, $args)) {
                $fluentFields[$field['id']] = $value;
            }
        }

        $submitBtn = $this->getSubmitBttn([
            'uniqElKey' => time(),
            'class' => '',
            'label' => ArrayHelper::get($form, 'button.text', 'Submit'),
            'type' => ArrayHelper::get($form, 'button.type') == 'text' ? 'default' : 'image',
            'img_url' => ArrayHelper::get($form, 'button.imageUrl'),
        ]);

        $returnData = [
            'fields' => $this->getContainer($fields, $fluentFields),
            'submitButton' => $submitBtn
        ];

        if ($this->hasStep && defined('FLUENTFORMPRO')) {
            $returnData['stepsWrapper'] = $this->getStepWrapper($form);
        }

        return $returnData;
    }

    private function formatFieldData(array $field)
    {
        $args = [
            'uniqElKey' => $field['id'],
            'index' => $field['id'],
            'required' => $field['isRequired'],
            'label' => $field['label'],
            'label_placement' => $this->getLabelPlacement($field),
            'admin_field_label' => ArrayHelper::get($field, 'adminLabel'),
            'name' => $this->getInputName($field),
            'placeholder' => $this->dynamicShortcodeConverter(ArrayHelper::get($field, 'placeholder')),
            'class' => $field['cssClass'],
            'value' => $this->dynamicShortcodeConverter(ArrayHelper::get($field, 'defaultValue')),
            'help_message' => ArrayHelper::get($field, 'description'),
        ];
        
        $type = ArrayHelper::get($this->fieldTypes(), $field['type'], '');
        
        switch ($type) {

            case 'input_name':
                $args['input_name_args'] = $field['inputs'];
                $args['input_name_args']['first_name']['name'] = $this->getInputName($field['inputs'][1]);
                $args['input_name_args']['middle_name']['name'] = $this->getInputName($field['inputs'][2]);
                $args['input_name_args']['last_name']['name'] = $this->getInputName($field['inputs'][3]);
                $args['input_name_args']['first_name']['label'] = ArrayHelper::get($field['inputs'][1], 'label');
                $args['input_name_args']['middle_name']['label'] = ArrayHelper::get($field['inputs'][2], 'label');
                $args['input_name_args']['last_name']['label'] = ArrayHelper::get($field['inputs'][3], 'label');
                $args['input_name_args']['first_name']['visible'] = ArrayHelper::get($field, 'inputs.1.isHidden', true);
                $args['input_name_args']['middle_name']['visible'] = ArrayHelper::get($field, 'inputs.2.isHidden',
                    true);
                $args['input_name_args']['last_name']['visible'] = ArrayHelper::get($field, 'inputs.3.isHidden', true);
                break;
            case 'input_textarea':
                $args['maxlength'] = $field['maxLength'];
                break;
            case 'input_text':
                $args['maxlength'] = $field['maxLength'];
                $args['is_unique'] = ArrayHelper::isTrue($field, 'noDuplicates');
                if (ArrayHelper::isTrue($field, 'inputMask')) {
                    $type = 'input_mask';
                    $args['temp_mask'] = 'custom';
                    $args['mask'] = $field['inputMaskValue'];
                }
                if (ArrayHelper::isTrue($field, 'enablePasswordInput')) {
                    $type = 'input_password';
                }
                break;
            case 'address':
                $args['address_args'] = $this->getAddressArgs($field);
                break;
            case 'select':
            case 'input_radio':
                $optionData = $this->getOptions(ArrayHelper::get($field, 'choices'));
                $args['options'] = ArrayHelper::get($optionData, 'options');
                $args['value'] = ArrayHelper::get($optionData, 'selectedOption.0');
            case 'multi_select':
            case 'input_checkbox':
                $optionData = $this->getOptions(ArrayHelper::get($field, 'choices'));
                $args['options'] = ArrayHelper::get($optionData, 'options');
                $args['value'] = ArrayHelper::get($optionData, 'selectedOption');

                break;
            case 'input_date':
                if ($field['type'] == 'time') {
                    $args['format'] = 'H:i';
                    $args['is_time_enabled'] = true;
                }
                break;
            case 'input_number':
                $args['min'] = $field['rangeMin'];
                $args['max'] = $field['rangeMax'];
                break;
            case 'repeater_field':
                $repeaterFields = ArrayHelper::get($field, 'choices', []);
                $args['fields'] = $this->getRepeaterFields($repeaterFields, $field['label']);;
            case 'input_file':
                $args['allowed_file_types'] = $this->getFileTypes($field, 'allowedExtensions');
                $args['max_size_unit'] = 'MB';
                $args['max_file_size'] = $this->getFileSize($field);;
                $args['max_file_count'] = ArrayHelper::isTrue($field,
                    'multipleFiles') ? $field['maxFiles'] : 1; 
                $args['upload_btn_text'] = 'File Upload';
                break;
            case 'custom_html':
                $args['html_codes'] = $this->dynamicShortcodeConverter($field['content']);
                break;
            case 'section_break':
                $args['section_break_desc'] = $this->dynamicShortcodeConverter($field['description']);
                break;
            case 'terms_and_condition':
                $args['tnc_html'] = $field['description'];
                break;
            case 'form_step':
                $this->hasStep = true;
                $args['next_btn'] = $field['nextButton'];
                $args['next_btn']['type'] = $field['nextButton']['type'] == 'text' ? 'default' : 'img';
                $args['next_btn']['img_url'] = $field['nextButton']['imageUrl'];
                $args['prev_btn'] = $field['previousButton'];
                $args['prev_btn']['type'] = $field['previousButton']['type'] == 'text' ? 'default' : 'img';
                $args['prev_btn']['img_url'] = $field['previousButton']['imageUrl'];

                break;
        }
        return array($type, $args);

    }


    private function getInputName($field)
    {
        return str_replace('-', '_', sanitize_title($field['label'] . '-' . $field['id']));
    }

    private function getLabelPlacement($field) {
        if($field['labelPlacement'] == 'hidden_label') {
            return 'hide_label';
        }
        return 'top';
    }

    /**
     * @param $field
     * @return filesize in MB
     */
    private function getFileSize($field) {
        $fileSizeByte = ArrayHelper::get($field, 'maxFileSize', 10);

        if(empty($fileSizeByte)) {
            $fileSizeByte = 1;
        }      

        $fileSizeMB = ceil($fileSizeByte * 1048576);  // 1MB = 1048576 Bytes

        return $fileSizeMB;
    }

    /**
     * @return array
     */
    public function fieldTypes()
    {
        $fieldTypes = [
            'email' => 'email',
            'text' => 'input_text',
            'name' => 'input_name',
            'hidden' => 'input_hidden',
            'textarea' => 'input_textarea',
            'website' => 'input_url',
            'phone' => 'phone',
            'select' => 'select',
            'list' => 'repeater_field',
            'multiselect' => 'multi_select',
            'checkbox' => 'input_checkbox',
            'radio' => 'input_radio',
            'date' => 'input_date',
            'time' => 'input_date',
            'number' => 'input_number',
            'fileupload' => 'input_file',
            'consent' => 'terms_and_condition',
            'captcha' => 'reCaptcha',
            'html' => 'custom_html',
            'section' => 'section_break',
            'page' => 'form_step',
            'address' => 'address',
        ];
        //todo pro fields remove
        return $fieldTypes;
    }

    /**
     * @param array $field
     * @return array[]
     */
    private function getAddressArgs(array $field)
    {
        return [
            'address_line_1' => [
                'name' => $this->getInputName($field['inputs'][0]),
                'label' => $field['inputs'][0]['label'],
                'visible' => ArrayHelper::get($field, 'inputs.0.isHidden', true),
            ],
            'address_line_2' => [
                'name' => $this->getInputName($field['inputs'][1]),
                'label' => $field['inputs'][1]['label'],
                'visible' => ArrayHelper::get($field, 'inputs.1.isHidden', true),
            ],
            'city' => [
                'name' => $this->getInputName($field['inputs'][2]),
                'label' => $field['inputs'][2]['label'],
                'visible' => ArrayHelper::get($field, 'inputs.2.isHidden', true),
            ],
            'state' => [
                'name' => $this->getInputName($field['inputs'][3]),
                'label' => $field['inputs'][3]['label'],
                'visible' => ArrayHelper::get($field, 'inputs.3.isHidden', true),
            ],
            'zip' => [
                'name' => $this->getInputName($field['inputs'][4]),
                'label' => $field['inputs'][4]['label'],
                'visible' => ArrayHelper::get($field, 'inputs.4.isHidden', true),
            ],
            'country' => [
                'name' => $this->getInputName($field['inputs'][5]),
                'label' => $field['inputs'][5]['label'],
                'visible' => ArrayHelper::get($field, 'inputs.5.isHidden', true),
            ],
        ];
    }

    /**
     * @param $options
     * @return array
     */
    public function getOptions($options = [])
    {
        $formattedOptions = [];
        $selectedOption = [];
        foreach ($options as $key => $option) {
            $arr = [
                'label' => ArrayHelper::get($option, 'text', 'Item -' . $key),
                'value' => ArrayHelper::get($option, 'value'),
                'id' => ArrayHelper::get($option, $key)
            ];
            if (ArrayHelper::isTrue($option, 'isSelected')) {
                $selectedOption[] = ArrayHelper::get($option, 'value', '');
            }
            $formattedOptions[] = $arr;
        }

        return ['options' => $formattedOptions, 'selectedOption' => $selectedOption];
    }

    /**
     * @param $repeaterFields
     * @param $label
     * @return array
     */
    protected function getRepeaterFields($repeaterFields, $label)
    {
        $arr = [];
        if (empty($repeaterFields)) {
            $arr[] = [
                'element' => 'input_text',
                'attributes' => array(
                    'type' => 'text',
                    'value' => '',
                    'placeholder' => '',
                ),
                'settings' => array(
                    'label' => $label,
                    'help_message' => '',
                    'validation_rules' => array(
                        'required' => array(
                            'value' => false,
                            'message' => __('This field is required', 'fluentform'),
                        ),
                    )
                )
            ];
        } else {
            foreach ($repeaterFields as $serial => $repeaterField) {
                $arr[] = [
                    'element' => 'input_text',
                    'attributes' => array(
                        'type' => 'text',
                        'value' => '',
                        'placeholder' => '',
                    ),
                    'settings' => array(
                        'label' => $repeaterField['label'],
                        'help_message' => '',
                        'validation_rules' => array(
                            'required' => array(
                                'value' => false,
                                'message' => __('This field is required', 'fluentform'),
                            ),
                        )
                    )
                ];

            }
        }
        return $arr;
    }

    private function getContainer($fields, $fluentFields)
    {

        $layoutGroupIds = array_column($fields, 'layoutGroupId');
        $cols = array_count_values($layoutGroupIds); // if inputs has more then one duplicate layoutGroupIds then it has container
        if (max($cols) < 2) {
            return $fluentFields;
        }

        $final = [];
        //get fields array for inserting into containers
        $containers = self::getLayout($fields);

        //set fields array map for inserting into containers
        foreach ($containers as $index => $fields) {
            $final[$index][] = array_map(function ($id) use ($fluentFields) {
                if (isset($fluentFields[$id])) {
                    return $fluentFields[$id];
                }
            }, $fields);
        }
        $final = self::arrayFlat($final);
        $withContainer = [];
        foreach ($final as $row => $columns) {
            $colsCount = count($columns);
            $containerConfig = [];
            //with container
            if ($colsCount != 1) {

                $fields = [];
                foreach ($columns as $col) {
                    $fields[]['fields'] = [$col];
                }

                $containerConfig[] = [
                    'index' => $row,
                    'element' => 'container',
                    "attributes" => [],
                    'settings' => [
                        'container_class',
                        'conditional_logics'
                    ],
                    'editor_options' => [
                        'title' => $colsCount . ' Column Container',
                        'icon_class' => 'ff-edit-column-' . $colsCount
                    ],
                    'columns' => $fields,
                    'uniqElKey' => 'col' . '_' . md5(uniqid(mt_rand(), true))
                ];
            } else {
                //without container
                $containerConfig = $columns;

            }
            $withContainer[] = $containerConfig;
        }
        return (array_filter(self::arrayFlat($withContainer)));
    }

    protected static function getLayout($fields, $id = '')
    {
        $layoutGroupIds = array_column($fields, 'layoutGroupId');
        $rows = array_count_values($layoutGroupIds);
        $layout = [];
        foreach ($rows as $key => $value) {
            $layout[] = self::getInputIdsFromLayoutGrp($key, $fields);
        }
        return $layout;
    }

    public static function getInputIdsFromLayoutGrp($id, $array)
    {
        $keys = [];
        foreach ($array as $key => $val) {
            if ($val['layoutGroupId'] === $id) {
                $keys[] = $val['id'];
            }
        }
        return $keys;
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
    private function getStepWrapper($form)
    {
        
        if($form['pagination']['type'] === 'steps') {
            $progressBar = 'steps';
        } else if($form['pagination']['type'] === 'none') {
            $progressBar = '';
        } else {
            $progressBar = 'progress-bar';
        }

        return [
            'stepStart' => [
                'element' => 'step_start',
                'attributes' => [
                    'id' => '',
                    'class' => $form['firstPageCssClass'],
                ],
                'settings' => [
                    'progress_indicator' => $progressBar,
                    'step_titles' => $form['pagination']['pages'],
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
                        'type' => $form['lastPageButton']['type'] == 'image' ? 'img' : 'default',
                        'text' => $form['lastPageButton']['text'],
                        'img_url' => $form['lastPageButton']['imageUrl']
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

        $array = array_reverse($form['confirmations']);
        $firstConfirmation = array_pop($array);
        $confirmation = wp_parse_args(
            [
                'messageToShow' => $this->dynamicShortcodeConverter($firstConfirmation['message']),
                'samePageFormBehavior' => 'hide_form',
            ], $defaults['confirmation']
        );
        
        $defaults['layout']['labelPlacement'] = ArrayHelper::get($form,
        'labelPlacement') == 'left_label' ? 'left' : (ArrayHelper::get($form,
        'labelPlacement') == 'right_label' ? 'right' : 'top');

        $defaults['restrictions']['requireLogin']['enabled'] = ArrayHelper::isTrue($form, 'requireLogin');
        $defaults['restrictions']['requireLogin']['requireLoginMsg'] = ArrayHelper::get($form,
            'requireLoginMessage');
        $defaults['restrictions']['limitNumberOfEntries']['enabled'] = ArrayHelper::isTrue($form, 'limitEntries');
        $defaults['restrictions']['limitNumberOfEntries']['numberOfEntries'] = ArrayHelper::get($form,
            'limitEntriesCount');
        $defaults['restrictions']['limitNumberOfEntries']['period'] = ArrayHelper::get($form, 'limitEntriesPeriod');
        $defaults['restrictions']['limitNumberOfEntries']['limitReachedMsg'] = ArrayHelper::get($form,
            'limitEntriesMessage');
        $defaults['restrictions']['scheduleForm']['enabled'] = ArrayHelper::isTrue($form, 'scheduleForm');
        $defaults['restrictions']['scheduleForm']['start'] = ArrayHelper::get($form, 'scheduleStart');
        $defaults['restrictions']['scheduleForm']['end'] = ArrayHelper::get($form, 'scheduleEnd');
        $defaults['restrictions']['scheduleForm']['pendingMsg'] = ArrayHelper::get($form, 'schedulePendingMessage');
        $defaults['restrictions']['scheduleForm']['expiredMsg'] = ArrayHelper::get($form, 'scheduleMessage');

        $deleteEntries = [
            'deleteEnabled' => $form['personalData']['retention']['policy'] !== 'retain',
            'deleteDays' => $form['personalData']['retention']['retain_entries_days']
        ];

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

        $notifications = [];
        foreach ($form['notifications'] as $notification) {

            // Convert shortcodes in email notification
            $notification['name'] = $this->dynamicShortcodeConverter($notification['name']);
            $notification['subject'] = $this->dynamicShortcodeConverter($notification['subject']);
            $notification['to'] = $this->dynamicShortcodeConverter($notification['to']);
            $notification['replyTo'] = $this->dynamicShortcodeConverter($notification['replyTo']);
            $notification['message'] = $this->dynamicShortcodeConverter($notification['message']);
            $notification['fromName'] = $this->dynamicShortcodeConverter($notification['fromName']);
            $notification['from'] = $this->dynamicShortcodeConverter($notification['from']);
            $notification['bcc'] = $this->dynamicShortcodeConverter($notification['bcc']);

            $notifications[] =
                [
                    'sendTo' => [
                        'type' => 'email',
                        'email' => $notification['to'],
                        'field' => '',
                        'routing' => [],
                    ],
                    'enabled' => ArrayHelper::isTrue($notification, 'isActive'),
                    'name' => $notification['name'],
                    'subject' => $notification['subject'],
                    'to' => $notification['to'],
                    'replyTo' => $notification['replyTo'],
                    'message' => $notification['message'],
                    'fromName' => $notification['fromName'],
                    'fromEmail' => $notification['from'],
                    'bcc' => $notification['bcc'],
                    'cc' => ArrayHelper::get($notification, 'cc'),
                ];

        }

        return [
            'formSettings' => [
                'confirmation' => $confirmation,
                'restrictions' => $defaults['restrictions'],
                'layout' => $defaults['layout'],
                'delete_after_x_days' => $deleteEntries['deleteEnabled'],
                'auto_delete_days' => $deleteEntries['deleteDays']
            ],
            'advancedValidationSettings' => $advancedValidation,
            'delete_entry_on_submission' => 'no',
            'notifications' => $notifications
        ];
    }

    
    /**
     * Convert Gravity Forms merge Tags to Fluent forms dynamic shortcodes.
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
            'user:' => 'user.',
            '{date_dmy}' => '{date.d/m/Y}',
            '{date_mdy}' => '{date.m/d/Y}',
            'embed_post:' => 'embed_post.',
            '{embed_url}' => '{embed_post.permalink}',
            '{referer}' => '{http_referer}',
            '{user_agent}' => '',
            '{all_fields}' => '{all_data}',
            '{admin_email}' => '{wp.admin_email}'
        ];

        return $dynamicShortcodes;
    }

    protected function getForms()
    {
        $forms = GFAPI::get_forms();
        return $forms;
    }

    protected function getFormName($form)
    {
        return $form['title'];
    }

    /**
     * @param $form
     * @return mixed
     */
    protected function getFormId($form)
    {
        return $form['ID'];
    }

}
