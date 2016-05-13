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
// second parameter of loadDay method can be either channel name or it's id
$data = $parser->loadDay(date('Y-m-d'),'TVN 7');

//also you can pass the third argument - channel url(This will make parsing faster because of no need to get all channels list beforehand)
$data = $parser->loadDay(date('Y-m-d'),'TVN 7','program-tv/stacje/TVN-Siedem');
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

Also you can get additional information on each program. There are 2 ways to do that:

```
$parser = new EpgParser();
$data = $parser->loadDay(date('Y-m-d'),'TVN 7');
if($data){
    $programs = $parser->parseDaySchedule($data,true);//second parameter set to true
}

```

Here you will get an array with all day channel's programs extended by additional fields(`rating`,`actors`,`director`,`short_descr`,`description`)


Or you can get additional info separately this way:

```
$parser = new EpgParser();
$page = $parser->getProgramInfo("http://www.teleman.pl/tv/Rajska-Jablon-783885");
var_dump($p->parseProgramData($page));

```

Result will be:


```
array(6) {
  ["genre"]=>
  string(17) "dramat obyczajowy"
  ["rating"]=>
  string(2) "16"
  ["actors"]=>
  string(70) "Marta Klubowicz, Izabela Drobotowicz-Orkisz, Ewa Kasprzyk, Piotr Bajor"
  ["director"]=>
  string(12) "Barbara Sass"
  ["short_descr"]=>
  string(52) "Zawikłane losy czterech przyjaciółek z Nowolipek."
  ["description"]=>
  string(1611) "Dalsze losy bohaterów filmu "Dziewczęta z Nowolipek", według powieści Poli Gojawiczyńskiej "Rajska jabłoń". Warszawa, początek lat 20. XX wieku. Kwiryna (Ewa Kasprzyk) i jej dawny adorator Roman (Piotr Bajor) są już małżeństwem. Wspólnie prowadzą sklep, w którym często pojawia się uboga, bardzo atrakcyjna blondynka (Danuta Kowalska). Podczas gdy Roman próbuje pomóc żyjącej w ubóstwie klientce, Kwiryna staje się o nią zazdrosna. Tymczasem Amelka (Marta Klubowicz) wiedzie spokojne życie jako żona aptekarza Filipa (Mariusz Dmochowski). Bronka (Izabela Drobotowicz-Orkisz) z kolei pracuje w Towarzystwie Dobroczynności. Jej zwierzchniczką jest Magdalena Piędzicka (Anna Romantowska), żona Ignasia, dawnego ukochanego Mossakowskiej. Kobieta uważa, że zachowanie Bronki wobec mężczyzn jest niestosowane. W odpowiedzi na jej uwagi Bronka składa wymówienie. Wychodząc z domu byłej szefowej, przypadkiem spotyka Ignacego (Krzysztof Kolberger). Z jego inicjatywy oboje zaczynają się spotykać, a łączące ich niegdyś uczucie odżywa. Mężczyzna nie zamierza jednak porzucić żony. W odwecie Bronka zaczyna spotykać się z innymi panami. Tymczasem Amelka, po skończonym romansie z bratankiem proboszcza Andrzejem (Andrzej Grabarczyk), zostaje uwiedziona przez nowego pracownika apteki, prowizora Jana Cichockiego (Jan Englert). Gdy wyznaje mężowi prawdę o swoim romansie, ten prosi ją o tabletkę na uspokojenie. Amelka wsypuje do szklanki całą zawartość fiolki weronalu. W nocy aptekarz umiera, a podejrzana o otrucie go Amelka zasiada na ławie oskarżonych."
}

```

