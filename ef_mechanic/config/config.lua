Config = Config or {}

---Default locale for notifies and NUI copy (`de` | `en`).
Config.Locale = 'de'

---Command to open the workshop tablet. Keep prefixed to avoid collisions.
Config.TabletCommand = 'workshop'

---Item that opens the tablet when used.
Config.TabletItem = 'ef_workshop_tablet'

---Pay invoices from bank first, then cash.
Config.PreferBankPayment = true

---When false, invoice money goes to the location society / internal account.
Config.PayEmployeeDirectly = false

---Use framework jobs for owned locations (ESX job name + grade).
Config.UseFrameworkJobs = true

---Society account provider for owned locations.
---`esx_addonaccount` | `internal`
Config.SocietyProvider = 'esx_addonaccount'

---Mileage sample interval while driving (ms). Never use Wait(0) loops.
Config.MileageIntervalMs = 7000

---Ignore teleports above this delta in meters when accumulating km.
Config.MileageMaxDeltaMeters = 120.0

---Debug prints (server console). Keep false in production.
Config.Debug = false
