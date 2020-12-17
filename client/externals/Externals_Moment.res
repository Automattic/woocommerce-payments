type t

@bs.module("moment")
external moment: t = "default"

@bs.send
external utc: (t, float) => t = "utc"

@bs.send
external toISOString: t => string = "toISOString"
