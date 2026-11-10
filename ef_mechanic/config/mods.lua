Config = Config or {}

---Category metadata for the tuning UI. Prices live on each location.
Config.ModCategories = {
    repair = { labelKey = 'mod_repair', requiresItem = false },
    performance = { labelKey = 'mod_performance', requiresItem = false, itemName = 'ef_performance_part' },
    cosmetics = { labelKey = 'mod_cosmetics', requiresItem = false, itemName = 'ef_cosmetic_part' },
    respray = { labelKey = 'mod_respray', requiresItem = false, itemName = 'ef_paint_kit' },
    wheels = { labelKey = 'mod_wheels', requiresItem = false, itemName = 'ef_wheel_set' },
    neonLights = { labelKey = 'mod_neon', requiresItem = false },
    headlights = { labelKey = 'mod_headlights', requiresItem = false },
    tyreSmoke = { labelKey = 'mod_tyre_smoke', requiresItem = false },
    bulletproofTyres = { labelKey = 'mod_bulletproof_tyres', requiresItem = false },
    extras = { labelKey = 'mod_extras', requiresItem = false },
}

---Repair kit behaviour (implemented in later milestones).
Config.RepairKit = {
    itemName = 'ef_repair_kit',
    durationMs = 8000,
    fullRepair = true,
}

Config.TempRepair = {
    itemName = 'ef_temp_repair',
    durationMs = 5000,
    -- Expiry is enforced server-side (km or minutes).
    expireAfterKm = 15.0,
    expireAfterMinutes = 20,
    cooldownSeconds = 300,
}
