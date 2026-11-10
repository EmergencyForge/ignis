EFMechanic = EFMechanic or {}

local function logFramework()
    local name = EFMechanic.Framework.GetName()
    if name == 'esx' then
        print(('[ef_mechanic] %s'):format(EFMechanic.T('bridge_framework_esx')))
    elseif name == 'qb' then
        print(('[ef_mechanic] %s'):format(EFMechanic.T('bridge_framework_qb')))
    elseif name == 'qbox' then
        print(('[ef_mechanic] %s'):format(EFMechanic.T('bridge_framework_qbox')))
    else
        print(('[ef_mechanic] %s'):format(EFMechanic.T('bridge_framework_none')))
    end
end

AddEventHandler('onResourceStart', function(resourceName)
    if resourceName ~= GetCurrentResourceName() then
        return
    end

    print(('[ef_mechanic] %s'):format(EFMechanic.T('resource_started')))
    logFramework()

    if EFMechanic.Db and EFMechanic.Db.EnsureSchema then
        EFMechanic.Db.EnsureSchema()
    end
end)

---Garage / vehicle-state hook reserved for later milestones.
---@param _vehicle number
---@param plate string
exports('ApplyVehicleState', function(_vehicle, plate)
    plate = EFMechanic.NormalizePlate(plate)
    if plate == '' then
        return false
    end
    -- M0: export exists so garages can depend on a stable name early.
    return true
end)
