-- luacheck: std lua51, globals Counters Queue Inventory JsonShipper KEYS
require('Counters')
require('Inventory')
require('Queue')
require('JsonShipper')

local prefix = 'rrd'
local counters = Counters.new(prefix)
-- local pushResult = JsonShipper.new(...) -> combine with result?
JsonShipper.new(
    Inventory.new(prefix),
    Queue.new(prefix, counters)
).pushJsonBulk(KEYS)
local result = counters.getPending()
counters.flush()
return result
