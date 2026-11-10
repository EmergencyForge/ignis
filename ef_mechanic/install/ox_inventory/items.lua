--[[
  Example ox_inventory item definitions for ef_mechanic.
  Copy the entries you need into your ox_inventory/data/items.lua.
  Provide your own icons — do not reuse third-party paid pack art.
]]

return {
    ['ef_workshop_tablet'] = {
        label = 'Werkstatt-Tablet',
        weight = 400,
        stack = false,
        close = true,
        description = 'Tablet für Werkstatt-Aufträge und Service.',
    },
    ['ef_engine_oil'] = {
        label = 'Motoröl',
        weight = 1000,
        stack = true,
        close = true,
        description = 'Service-Teil für Verbrennungsmotoren.',
    },
    ['ef_air_filter'] = {
        label = 'Luftfilter',
        weight = 500,
        stack = true,
        close = true,
        description = 'Service-Teil für Verbrennungsmotoren.',
    },
    ['ef_spark_plugs'] = {
        label = 'Zündkerzen',
        weight = 200,
        stack = true,
        close = true,
        description = 'Service-Teil für Verbrennungsmotoren.',
    },
    ['ef_clutch'] = {
        label = 'Kupplung',
        weight = 2500,
        stack = true,
        close = true,
        description = 'Service-Teil für Verbrennungsmotoren.',
    },
    ['ef_brake_pads'] = {
        label = 'Bremsbeläge',
        weight = 800,
        stack = true,
        close = true,
        description = 'Service-Teil für Bremsen.',
    },
    ['ef_tyre_set'] = {
        label = 'Reifensatz',
        weight = 4000,
        stack = true,
        close = true,
        description = 'Satz Reifen für den Service.',
    },
    ['ef_suspension_kit'] = {
        label = 'Fahrwerk-Kit',
        weight = 3500,
        stack = true,
        close = true,
        description = 'Service-Teil für das Fahrwerk.',
    },
    ['ef_repair_kit'] = {
        label = 'Reparatur-Kit',
        weight = 1500,
        stack = true,
        close = true,
        description = 'Repariert Fahrzeugschäden.',
    },
    ['ef_temp_repair'] = {
        label = 'Notreparatur',
        weight = 400,
        stack = true,
        close = true,
        description = 'Temporäre Notlösung — hält nur kurz.',
    },
    ['ef_performance_part'] = {
        label = 'Performance-Teil',
        weight = 1000,
        stack = true,
        close = true,
        description = 'Optionales Teil, wenn Locations requiresItem nutzen.',
    },
    ['ef_cosmetic_part'] = {
        label = 'Kosmetik-Teil',
        weight = 500,
        stack = true,
        close = true,
        description = 'Optionales Teil, wenn Locations requiresItem nutzen.',
    },
    ['ef_paint_kit'] = {
        label = 'Lack-Kit',
        weight = 800,
        stack = true,
        close = true,
        description = 'Optionales Teil für Lackierungen.',
    },
    ['ef_wheel_set'] = {
        label = 'Felgensatz',
        weight = 3000,
        stack = true,
        close = true,
        description = 'Optionales Teil für Felgenwechsel.',
    },
}
