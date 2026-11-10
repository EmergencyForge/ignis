EFMechanic = EFMechanic or {}
EFMechanic.Notify = EFMechanic.Notify or {}

local Notify = EFMechanic.Notify

---@param source number|nil nil = local client
---@param description string
---@param nType 'inform'|'success'|'error'|'warning'|nil
---@param duration number|nil
function Notify.Send(source, description, nType, duration)
    nType = nType or 'inform'
    duration = duration or 5000

    if IsDuplicityVersion() then
        if not source then
            return
        end
        TriggerClientEvent('ox_lib:notify', source, {
            description = description,
            type = nType,
            duration = duration,
        })
        return
    end

    lib.notify({
        description = description,
        type = nType,
        duration = duration,
    })
end
