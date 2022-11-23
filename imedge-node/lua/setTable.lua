require('LuaUtils')
require('RedisTable')

-- luacheck: std lua51, globals redis RedisTable LuaUtils KEYS ARGV
local tableName = KEYS[1]
local checksumLength = KEYS[2]
local devicePrefix = KEYS[3]
local keyProperties = KEYS[4]

return RedisTable.new(tableName, checksumLength, keyProperties).setPrefixedRows(devicePrefix, LuaUtils.dict(ARGV))
