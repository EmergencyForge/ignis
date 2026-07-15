<?php

// Invalid on purpose: missing the required 'name' and 'version' fields.
// fromDirectory() must record this as skipped, not fatal.
return [
    'id' => 'broken',
];
