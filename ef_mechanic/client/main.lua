EFMechanic = EFMechanic or {}

local function onResourceStart(resourceName)
    if resourceName ~= GetCurrentResourceName() then
        return
    end

    if Config.Debug then
        print(('[ef_mechanic] client ready — framework=%s'):format(
            EFMechanic.Framework.GetName() or 'none'
        ))
    end
end

AddEventHandler('onClientResourceStart', onResourceStart)

RegisterCommand(Config.TabletCommand or 'workshop', function()
    EFMechanic.OpenTablet()
end, false)

TriggerEvent('chat:addSuggestion', ('/%s'):format(Config.TabletCommand or 'workshop'), EFMechanic.T('command_workshop_help'))
