EFMechanic = EFMechanic or {}

function EFMechanic.OpenTablet()
    if EFMechanic.IsNuiOpen() then
        return
    end

    EFMechanic.SetNuiFocus(true)
    EFMechanic.SendNui({
        action = 'open',
        locale = Config.Locale or 'de',
        labels = {
            title = EFMechanic.T('tablet_title'),
            placeholder = EFMechanic.T('tablet_placeholder'),
            overview = EFMechanic.T('app_overview'),
            servicing = EFMechanic.T('app_servicing'),
            invoices = EFMechanic.T('app_invoices'),
            register = EFMechanic.T('app_register'),
            settings = EFMechanic.T('app_settings'),
        },
    })

    EFMechanic.Notify.Send(nil, EFMechanic.T('tablet_open'), 'inform')
end

function EFMechanic.CloseTablet()
    if not EFMechanic.IsNuiOpen() then
        return
    end

    EFMechanic.SendNui({ action = 'close' })
    EFMechanic.SetNuiFocus(false)
    EFMechanic.Notify.Send(nil, EFMechanic.T('tablet_close'), 'inform')
end
