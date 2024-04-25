<?php

namespace MediactiveDigital\MedKit\Traits;

use Eloquent;
use Str;

trait Model {

    /**
     * Custom attributes.
     *
     * @var array
     */
    protected $customAttributes = [];

    /**
     * Human readable name.
     *
     * @var string
     */
    public $nameForHumans = null;

    /**
     * Get an attribute from the model.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getAttribute($key) {

        if (!$key) {

            return;
        }

        // If the attribute exists in the attribute array or has a "get" mutator we will
        // get the attribute's value. Otherwise, we will proceed as if the developers
        // are asking for a relationship's value. This covers both types of values.
        if ($this->hasAttribute($key)) {

            return $this->getAttributeValue($key);
        }

        // Here we will determine if the model base class itself contains this given key
        // since we don't want to treat any of those methods as relationships because
        // they are all intended as helper methods and none of these are relations.
        if (method_exists(Eloquent::class, $key)) {

            return $this->throwMissingAttributeExceptionIfApplicable($key);
        }

        if ($this->isRelation($key) || $this->relationLoaded($key)) {

            return $this->getRelationValue($key);
        }

        // Custom attributes.
        return $this->getCustomAttributeValue($key, true);
    }

    /**
     * Set a given attribute on the model.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return mixed
     */
    public function setAttribute($key, $value) {

        // First we will check for the presence of a mutator for the set operation
        // which simply lets the developers tweak the attribute as it is set on
        // this model, such as "json_encoding" a listing of data for storage.
        if ($this->hasSetMutator($key)) {

            return $this->setMutatedAttributeValue($key, $value);
        }
        else if ($this->hasAttributeSetMutator($key)) {

            return $this->setAttributeMarkedMutatedAttributeValue($key, $value);
        }

        // If an attribute is listed as a "date", we'll convert it from a DateTime
        // instance into a form proper for storage on the database tables using
        // the connection grammar's date format. We will auto set the values.
        else if (!is_null($value) && $this->isDateAttribute($key)) {

            $value = $this->fromDateTime($value);
        }

        if ($this->isEnumCastable($key)) {

            $this->setEnumCastableAttribute($key, $value);

            return $this;
        }

        if ($this->isClassCastable($key)) {

            $this->setClassCastableAttribute($key, $value);

            return $this;
        }

        if (!is_null($value) && $this->isJsonCastable($key)) {

            $value = $this->castAttributeAsJson($key, $value);
        }

        // If this attribute contains a JSON ->, we'll set the proper value in the
        // attribute's underlying array. This takes care of properly nesting an
        // attribute in the array's value in the case of deeply nested items.
        if (str_contains($key, '->')) {

            return $this->fillJsonAttribute($key, $value);
        }

        if (!is_null($value) && $this->isEncryptedCastable($key)) {

            $value = $this->castAttributeAsEncryptedString($key, $value);
        }

        if (!is_null($value) && $this->hasCast($key, 'hashed')) {

            $value = $this->castAttributeAsHashedString($key, $value);
        }

        // Custom attributes.
        if ($this->hasCustomAttribute($key)) {

            return $this->setCustomAttributeValue($key, $value);
        }

        $this->attributes[$key] = $value;

        return $this;
    }

    /**
     * Determine if a custom attribute exists.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasCustomAttribute(string $key): bool {

        return $this->customAttributeLoaded($key) || $this->hasCustomAttributeGetter($key) || $this->hasCustomAttributeSetter($key);
    }

    /**
     * Determine if a custom attribute getter exists.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasCustomAttributeGetter(string $key): bool {

        return method_exists($this, $this->getCustomAttributeGetter($key)) && !$this->hasIgnoredCustomAttribute($key);
    }

    /**
     * Determine if a custom attribute setter exists.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasCustomAttributeSetter(string $key): bool {

        return method_exists($this, $this->getCustomAttributeSetter($key)) && !$this->hasIgnoredCustomAttribute($key);
    }

    /**
     * Determine if a custom attribute must be ignored.
     *
     * @param string $key
     *
     * @return bool
     */
    public function hasIgnoredCustomAttribute(string $key): bool {

        return in_array($key, $this->getIgnoredCustomAttributes());
    }

    /**
     * Get ignored custom attributes list.
     *
     * @return array
     */
    public function getIgnoredCustomAttributes(): array {

        return [
            'created_at',
            'updated_at'
        ];
    }

    /**
     * Get custom attribute getter method.
     *
     * @param string $key
     *
     * @return string
     */
    public function getCustomAttributeGetter(string $key): string {

        return 'get' . Str::studly($key);
    }

    /**
     * Get custom attribute setter method.
     *
     * @param string $key
     *
     * @return string
     */
    public function getCustomAttributeSetter(string $key): string {

        return 'set' . Str::studly($key);
    }

    /**
     * Get the value of a custom attribute.
     *
     * @param string $key
     * @param bool $exception
     *
     * @return mixed
     */
    public function getCustomAttributeValue(string $key, bool $exception = false) {

        if (!($loaded = $this->customAttributeLoaded($key)) && $this->hasCustomAttributeGetter($key)) {

            $method = $this->getCustomAttributeGetter($key);

            if (($reflectionMethod = new \ReflectionMethod(self::class, $method)) && !$reflectionMethod->getNumberOfRequiredParameters()) {

                return $this->{$method}();
            }
        }

        return $loaded ? $this->getCustomAttribute($key) : ($exception ? $this->throwMissingAttributeExceptionIfApplicable($key) : null);
    }

    /**
     * Set the value of a custom attribute.
     *
     * @param string $key
     * @param mixed $value
     *
     * @return mixed
     */
    public function setCustomAttributeValue(string $key, $value) {

        return $this->setCustomAttribute($key, $value, false);
    }

    /**
     * Get the value of a custom attribute directly.
     *
     * @param string $key
     *
     * @return mixed
     */
    public function getCustomAttribute(string $key) {

        return $this->customAttributes[$key] ?? null;
    }

    /**
     * Set the value of a custom attribute directly.
     *
     * @param string $key
     * @param mixed $value
     * @param bool $exists
     *
     * @return mixed
     */
    public function setCustomAttribute(string $key, $value, bool $exists = true) {

        $this->customAttributes[$key] = !$exists || !$this->customAttributeLoaded($key) ? $value : $this->customAttributes[$key];

        return $this;
    }

    /**
     * Unset the value of a custom attribute directly.
     *
     * @param string $key
     *
     * @return void
     */
    public function unsetCustomAttribute(string $key) {

        unset($this->customAttributes[$key]);
    }

    /**
     * Determine if a custom attribute is loaded.
     *
     * @param string $key
     *
     * @return bool
     */
    public function customAttributeLoaded(string $key): bool {

        return array_key_exists($key, $this->customAttributes);
    }

    /**
     * Get the human readable name associated with the model.
     *
     * @return string
     */
    public function getNameForHumans(): string {

        $this->setNameForHumans($this->nameForHumans ?? str_replace('_', ' ', Str::singular($this->getTable())));

        return $this->nameForHumans;
    }

    /**
     * Set the human readable name associated with the model.
     *
     * @param string $nameForHumans
     *
     * @return mixed
     */
    public function setNameForHumans(string $nameForHumans) {

        $this->nameForHumans = $nameForHumans;

        return $this;
    }
}
