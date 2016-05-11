# EPG from teleman.pl

EPG parser teleman.pl

### Usage

 - Getting list of categories and channels
 
 ```
 require_once('vendor/autoload.php');
 
 use teleman/EpgParser;
 
 $parser = new EpgParser();
 // to get channels page
 $data = $parser->loadChannels();
 if($data){
    $parser->parseChannels($data);
    var_dump($parser->getCategories());
    var_dump($parser->getChannels());
 }
 
 ```

 - Getting EPG for one channel and one day

```
require_once('vendor/autoload.php');

use teleman/EpgParser;

$parser = new EpgParser();
// to get all channels programs for day
$data = $parser->loadDay(date('Y-m-d'),'TVN 7');
if($data){
    $programs = $parser->parseDaySchedule($data);
}
```

`$programs` would contain multidimensional array like this one:

```
array(29) {
  [0]=>
  array(7) {
    ["start"]=>
    string(4) "6:00"
    ["url"]=>
    string(31) "/tv/Taki-Jest-Swiat-348-1452241"
    ["name"]=>
    string(22) "Taki jest świat (348)"
    ["genre"]=>
    string(20) "program informacyjny"
    ["descr"]=>
    string(143) "Przegląd wydarzeń z całego świata. Widzowie zobaczą nie tylko wiadomości polityczne i gospodarcze, ale także informacje z życia gwiazd."
    ["channel"]=>
    string(4) "Puls"
    ["dateStart"]=>
    string(10) "2016-05-12"
    ["length"]=>
    int(50)
  }
  [1]=>
  array(7) {
    ["start"]=>
    string(4) "6:50"
    ["url"]=>
    string(21) "/tv/Super-2-5-1477379"
    ["name"]=>
    string(12) "Super! (2/5)"
    ["genre"]=>
    string(18) "program edukacyjny"
    ["descr"]=>
    string(183) " Zwykłym ludziom na całym świecie przydarzają się ciekawe, zaskakujące sytuacje, niekiedy wywołujące napięcie, innym razem śmiech. Jarosław Budnik przybliża niesamowite..."
    ["channel"]=>
    string(4) "Puls"
    ["dateStart"]=>
    string(10) "2016-05-12"
    ["length"]=>
    int(30)
  }
  ...

```