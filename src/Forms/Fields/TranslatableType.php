<?php

namespace MediactiveDigital\MedKit\Forms\Fields;

use Kris\LaravelFormBuilder\Fields\FormField;

use Str;

class TranslatableType extends FormField {

    /**
     * Get the template, can be config variable or view path.
     *
     * @return string
     */
    protected function getTemplate() {

        return 'translatable';
    }

    /**
     * Overload class attribute option for field.
     *
     * @return array
     */
    protected function getClassOverload() {

        return [
            'form-control'
        ];
    }

    /**
     * Render the field.
     *
     * @param array $options
     * @param bool $showLabel
     * @param bool $showField
     * @param bool $showError
     * @return string
     */
    public function render(array $options = [], $showLabel = true, $showField = true, $showError = true) {

        $locales = config('laravel-gettext.supported-locales');
        $model = $this->parent->getModel();
        $type = $this->options['subtype'] == 'textarea' ? $this->options['subtype'] : 'input';
        $ckEditor = $type == 'textarea' ? config('laravel-form-builder.translatable_textarea_ck_editor') : null;
        $attributes = isset($this->options['attr']) ? $this->options['attr'] : [];
        $localesAttributes = false;
        $fields = [];

        foreach ($attributes as $key => $attribute) {

            if (in_array($key, $locales) && is_array($attribute)) {

                $localesAttributes = true;

                break;
            }
        }

        foreach ($locales as $locale) {

            $fields[$locale] = [];
            $value = $model ? $model->getTranslation($this->name, $locale) : null;
            $localeAttributes = $localesAttributes ? (isset($this->options['attr'][$locale]) && is_array($this->options['attr'][$locale]) ? $this->options['attr'][$locale] : []) : $attributes;
            $classes = isset($localeAttributes['class']) ? rtrim($localeAttributes['class']) : '';

            foreach ($this->getClassOverload() as $class) {

                $classes .= Str::contains($classes, $class) ? '' : ($classes ? ' ' : '') . $class;
            }

            $fields[$locale]['button'] = [
                'type' => 'button',
                'attributes' => [
                    'type' => 'button'
                ],
                'value' => $locale
            ];

            $fields[$locale]['field'] = [
                'type' => $type,
                'attributes' => array_merge($localeAttributes, [
                    'class' => $classes,
                    'name' => $this->name . '[' . $locale . ']'
                ])
            ];

            if ($this->options['subtype'] == 'textarea') {

                $fields[$locale]['field']['value'] = $value;
                $fields[$locale]['field']['attributes']['cols'] = 50;
                $fields[$locale]['field']['attributes']['rows'] = 10;
                $fields[$locale]['field']['ck_editor'] = $ckEditor;
            }
            else {

                $fields[$locale]['field']['attributes']['type'] = 'text';
                $fields[$locale]['field']['attributes']['value'] = $value;
            }
        }

        $this->options['value'] = $fields;
        unset($this->options['subtype']);

        return parent::render($options, $showLabel, $showField, $showError);
    }
}
