<?php
trait ExtraHandlerTrait {
    public function getExtra(): object {
        return $this->extra ??= new \stdClass();
    }
    public function saveExtra(): void {
        $this->update([
            'extra' => $this->extra
        ]);
    }
}