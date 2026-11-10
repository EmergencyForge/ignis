fx_version 'cerulean'
game 'gta5'
lua54 'yes'

name 'ef_mechanic'
author 'EmergencyForge'
description 'Clean-room workshop / mechanic resource (ESX-first)'
version '0.1.0'

shared_scripts {
    '@ox_lib/init.lua',
    'shared/constants.lua',
    'shared/utils.lua',
    'config/config.lua',
    'config/locations.lua',
    'config/servicing.lua',
    'config/mods.lua',
    'locales/de.lua',
    'locales/en.lua',
    'bridges/framework.lua',
    'bridges/inventory.lua',
    'bridges/target.lua',
    'bridges/notify.lua',
    'bridges/banking.lua',
}

client_scripts {
    'client/main.lua',
    'client/nui.lua',
    'client/tablet.lua',
}

server_scripts {
    '@oxmysql/lib/MySQL.lua',
    'server/main.lua',
    'server/db.lua',
    'server/security.lua',
}

ui_page 'nui/index.html'

files {
    'nui/index.html',
    'nui/assets/**',
}

dependencies {
    'oxmysql',
    'ox_lib',
    'ox_inventory',
}

escrow_ignore {
    'config/**',
    'locales/**',
    'sql/**',
    'install/**',
    'README.md',
}
