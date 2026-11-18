<?php

namespace App\Enums\Concerns;

trait LabelDariNilai
{
    public function getLabel(): string
    {
        return ucfirst(str_replace('_', ' ', $this->value));
    }

    public static function opsi(): array
    {
        $o = [];
        foreach (self::cases() as $c) {
            $o[$c->value] = $c->getLabel();
        }
        return $o;
    }
}
