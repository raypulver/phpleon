# phpleon
This is the PHP implementation of LEON. It works identically to the JavaScript version, except it exposes the functions `leon_encode` and `leon_decode`. A Channel object can be constructed by calling `new LEON\Channel($template)`. A Channel object contains an `encode` and a `decode` method, which can be used to serialize data according to the template. Type constants to be passed to a LEON\Channel are defined at the top of leon.php.

## Usage

```
require_once 'leon.php';
```

LEON provides special classes to serialize/deserialize special JavaScript values. The `LEON\Date` constructor accepts a timestamp which can be accessed via `$date->timetamp`. The `LEON\RegExp` constructor accepts a string representation of the RegExp which can be retrieved by calling its `toString()` method. The value of NaN is deserialized into an instance of `LEON\NaN` and undefined deserializes to an instance of `LEON\Undefined`. If you want to serialize/deserialize a `Buffer` object you must use the `LEON\StringBuffer` class, which provides all the little endian methods for reading and writing data that a Node.js `Buffer` provides.

## TODO
Make it so `LEON\StringBuffer` provides all the methods of a Node.js `Buffer` object, including the big endian methods.

## License
MIT
