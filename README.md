# phpleon
This is the PHP implementation of LEON. It works identically to the JavaScript version, except it exposes the functions `leon_encode` and `leon_decode`. A Channel object can be constructed by calling `new LEON_Channel($template)`. A Channel object contains an `encode` and a `decode` method, which can be used to serialize data according to the template. Type constants to be passed to a LEON_Channel are defined at the top of leon.php.

# Usage

```
require_once 'leon.php';
```

# License
MIT
