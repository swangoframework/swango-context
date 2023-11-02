<?php
trait NamedEnumBackedTrait {
    abstract public function getName(): string;
    public static function getNameMap(): array {
        static $name_map = null;
        if (! isset($name_map)) {
            $name_map = [];
            foreach (static::cases() as $status)
                $name_map[$status->value] = $status->getName();
        }
        return $name_map;
    }
    public static function getKeyNameObjectArray(): array {
        static $name_map_array = null;
        if (! isset($name_map_array)) {
            $name_map_array = [];
            foreach (static::cases() as $status)
                $name_map_array[] = $status->getKeyNameObject();
        }
        return $name_map_array;
    }
    public function getKeyNameObject(): object {
        $ob = new stdClass();
        $ob->name = $this->getName();
        $ob->key = $this->value;
        return $ob;
    }
    public function getKeyNamePairMap(): array {
        return [$this->value => $this->getName()];
    }
}