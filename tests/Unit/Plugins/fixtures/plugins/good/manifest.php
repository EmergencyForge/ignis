<?php

return [
    'id' => 'good',
    'name' => 'Good Plugin',
    'version' => '1.0.0',
    'requires' => ['ignis' => '>=1.0'],
    'autoload' => ['GoodPluginFixture\\' => 'src/'],
    'policies' => ['goodres' => 'GoodPluginFixture\\Policies\\GoodresPolicy'],
    'default_enabled' => true,
];
