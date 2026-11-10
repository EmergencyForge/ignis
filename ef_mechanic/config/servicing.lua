Config = Config or {}

---Spawn names that never receive mileage / wear (job fleet, etc.).
Config.ServicingBlacklist = {
    -- 'police',
    -- 'ambulance',
}

--[[
  Combustion parts for MVP. EV rows may exist as stubs for v2.
  handlingEffects are relative offsets applied once when health changes.
]]
Config.ServicingParts = {
    {
        id = 'engine_oil',
        labelKey = 'part_engine_oil',
        lifespanKm = 5000,
        itemName = 'ef_engine_oil',
        itemCount = 1,
        powertrain = 'combustion',
        handlingEffects = {
            fInitialDriveMaxFlatVel = -8.0,
            fDriveInertia = -0.05,
        },
    },
    {
        id = 'air_filter',
        labelKey = 'part_air_filter',
        lifespanKm = 8000,
        itemName = 'ef_air_filter',
        itemCount = 1,
        powertrain = 'combustion',
        handlingEffects = {
            fInitialDriveForce = -0.08,
            fInitialDriveMaxFlatVel = -5.0,
        },
    },
    {
        id = 'spark_plugs',
        labelKey = 'part_spark_plugs',
        lifespanKm = 10000,
        itemName = 'ef_spark_plugs',
        itemCount = 1,
        powertrain = 'combustion',
        handlingEffects = {
            fInitialDriveForce = -0.1,
        },
    },
    {
        id = 'clutch',
        labelKey = 'part_clutch',
        lifespanKm = 15000,
        itemName = 'ef_clutch',
        itemCount = 1,
        powertrain = 'combustion',
        handlingEffects = {
            fClutchChangeRateScaleUpShift = -1.5,
            fClutchChangeRateScaleDownShift = -1.5,
        },
    },
    {
        id = 'brake_pads',
        labelKey = 'part_brake_pads',
        lifespanKm = 12000,
        itemName = 'ef_brake_pads',
        itemCount = 1,
        powertrain = 'any',
        handlingEffects = {
            fBrakeForce = -0.25,
        },
    },
    {
        id = 'tyre_set',
        labelKey = 'part_tyre_set',
        lifespanKm = 20000,
        itemName = 'ef_tyre_set',
        itemCount = 1,
        powertrain = 'any',
        handlingEffects = {
            fTractionCurveMax = -0.3,
            fTractionCurveMin = -0.2,
        },
    },
    {
        id = 'suspension_kit',
        labelKey = 'part_suspension_kit',
        lifespanKm = 18000,
        itemName = 'ef_suspension_kit',
        itemCount = 1,
        powertrain = 'any',
        handlingEffects = {
            fSuspensionForce = -0.4,
            fAntiRollBarForce = -0.2,
        },
    },

    -- EV stubs (inactive until v2 wiring)
    {
        id = 'ev_motor',
        labelKey = 'part_ev_motor',
        lifespanKm = 25000,
        itemName = 'ef_ev_motor',
        itemCount = 1,
        powertrain = 'electric',
        handlingEffects = {},
        enabled = false,
    },
    {
        id = 'ev_battery',
        labelKey = 'part_ev_battery',
        lifespanKm = 30000,
        itemName = 'ef_ev_battery',
        itemCount = 1,
        powertrain = 'electric',
        handlingEffects = {},
        enabled = false,
    },
    {
        id = 'ev_coolant',
        labelKey = 'part_ev_coolant',
        lifespanKm = 15000,
        itemName = 'ef_ev_coolant',
        itemCount = 1,
        powertrain = 'electric',
        handlingEffects = {},
        enabled = false,
    },
}

---Models treated as electric powertrain (spawn names).
Config.ElectricModels = {
    -- 'voltic',
    -- 'cyclone',
}
