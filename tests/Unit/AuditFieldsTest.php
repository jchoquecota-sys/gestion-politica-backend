<?php

use App\Traits\HasAuditFields;
use App\Models\Actividad;
use App\Models\Base;
use App\Models\Sector;

test('models use HasAuditFields trait', function () {
    $models = [
        Actividad::class,
        Base::class,
        Sector::class,
    ];

    foreach ($models as $model) {
        $traits = class_uses_recursive($model);
        expect(in_array(HasAuditFields::class, $traits))->toBeTrue("Model {$model} does not use HasAuditFields trait");
    }
});

test('HasAuditFields trait defines audit relationship methods', function () {
    $reflection = new ReflectionClass(HasAuditFields::class);

    expect($reflection->hasMethod('creator'))->toBeTrue()
        ->and($reflection->hasMethod('updater'))->toBeTrue()
        ->and($reflection->hasMethod('deleter'))->toBeTrue()
        ->and($reflection->hasMethod('bootHasAuditFields'))->toBeTrue();
});
