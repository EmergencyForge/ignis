Config = Config or {}

--[[
  Example locations for Milestone 0 / early MVP wiring.
  Coordinates are placeholders — replace before going live.
]]
Config.Locations = {
    downtown_owned = {
        type = 'owned',
        job = 'mechanic',
        managementGrades = { 3, 4 },
        zones = {
            {
                coords = vector3(-337.0, -134.0, 39.0),
                size = vec3(12.0, 12.0, 4.0),
                showBlip = true,
                employeeOnly = false,
            },
        },
        blip = {
            sprite = 446,
            colour = 5,
            scale = 0.8,
            labelKey = 'blip_owned_workshop',
        },
        mods = {
            repair = { enabled = true, basePrice = 500 },
            performance = { enabled = true, basePrice = 1500 },
            cosmetics = { enabled = true, basePrice = 800 },
            respray = { enabled = true, basePrice = 600 },
            wheels = { enabled = true, basePrice = 900 },
            neonLights = { enabled = true, basePrice = 400 },
            headlights = { enabled = true, basePrice = 250 },
            tyreSmoke = { enabled = true, basePrice = 300 },
            bulletproofTyres = { enabled = true, basePrice = 2000 },
            extras = { enabled = true, basePrice = 200 },
        },
        shops = {
            {
                id = 'downtown_parts',
                coords = vector3(-345.0, -130.0, 39.0),
                size = vec3(1.5, 1.5, 2.0),
            },
        },
        stashes = {
            {
                id = 'downtown_stash',
                coords = vector3(-343.0, -128.0, 39.0),
                size = vec3(1.5, 1.5, 2.0),
                slots = 50,
                weight = 100000,
            },
        },
    },

    self_service_bay = {
        type = 'self-service',
        job = nil,
        managementGrades = {},
        zones = {
            {
                coords = vector3(731.0, -1088.0, 22.0),
                size = vec3(10.0, 10.0, 4.0),
                showBlip = true,
                employeeOnly = false,
            },
        },
        blip = {
            sprite = 72,
            colour = 3,
            scale = 0.7,
            labelKey = 'blip_self_service',
        },
        mods = {
            repair = { enabled = true, basePrice = 750 },
            performance = { enabled = true, basePrice = 2000 },
            cosmetics = { enabled = true, basePrice = 1000 },
            respray = { enabled = true, basePrice = 750 },
            wheels = { enabled = true, basePrice = 1100 },
            neonLights = { enabled = false, basePrice = 0 },
            headlights = { enabled = true, basePrice = 300 },
            tyreSmoke = { enabled = false, basePrice = 0 },
            bulletproofTyres = { enabled = false, basePrice = 0 },
            extras = { enabled = true, basePrice = 250 },
        },
        shops = {},
        stashes = {},
    },
}
