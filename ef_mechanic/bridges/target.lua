EFMechanic = EFMechanic or {}
EFMechanic.Target = EFMechanic.Target or {}

local Target = EFMechanic.Target

---@return boolean
function Target.IsAvailable()
    return GetResourceState('ox_target') == 'started'
        or GetResourceState('qb-target') == 'started'
end

---Scaffold stub — zone/entity targets land with Milestone 1 / 5.
---@param _opts table
function Target.AddBoxZone(_opts)
    -- no-op in M0
end

---@param _name string
function Target.RemoveZone(_name)
    -- no-op in M0
end
