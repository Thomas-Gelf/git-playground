require('RedisTable')

-- luacheck: std lua51, globals redis RedisTable KEYS ARGV
local tableName = KEYS[1]
return RedisTable.new(tableName).getTableWithStreamPosition()
