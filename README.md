# phpleon
This is the PHP implementation of LEON. It works identically to the JavaScript version, except it exposes the functions `leon_encode` and `leon_decode`. A Channel object can be constructed by calling `new LEON\Channel($template)`. A Channel object contains an `encode` and a `decode` method, which can be used to serialize data according to the template. Type constants to be passed to a LEON\Channel are defined at the top of leon.php.

## Usage

```
require_once 'leon.php';
$payload = array(
  'firstkey' => 5,
  'secondkey' => true,
  'thirdkey' => 'just a string'
);
leon_decode(leon_encode($payload)) == $payload;
// true
```

LEON provides special classes to serialize/deserialize special JavaScript values. The `LEON\Date` constructor accepts a timestamp which can be accessed via `$date->timetamp`. The `LEON\RegExp` constructor accepts a string representation of the RegExp (without the delimiting slashes) and can also take a second argument representing the RegExp modifiers. A RegExp object stores the match pattern in `$regexp->pattern` and the modifier in `$regexp->modifier`. A RegExp object also has a `toString()` method which behaves identically to JavaScript's `RegExp#toString()`.

The value of `NaN` is deserialized into an instance of `LEON\NaN` and `undefined` deserializes to an instance of `LEON\Undefined`. If you want to serialize/deserialize a `Buffer` object you must use the `LEON\StringBuffer` class, which provides all the methods that a Node.js `Buffer` provides for reading and writing data.

In addition to arrays, you can also serialize objects, but only public properties will be serialized, and it will treat the data as an associative array.

## License
MIT
