EFMechanic = EFMechanic or {}

local nuiOpen = false

---@param payload table
function EFMechanic.SendNui(payload)
    SendNUIMessage(payload)
end

---@param focus boolean
function EFMechanic.SetNuiFocus(focus)
    nuiOpen = focus
    SetNuiFocus(focus, focus)
end

---@return boolean
function EFMechanic.IsNuiOpen()
    return nuiOpen
end

RegisterNUICallback('close', function(_, cb)
    EFMechanic.CloseTablet()
    cb({ ok = true })
end)

RegisterNUICallback('ready', function(_, cb)
    cb({
        ok = true,
        locale = Config.Locale or 'de',
        resource = EFMechanic.ResourceName,
    })
end)
