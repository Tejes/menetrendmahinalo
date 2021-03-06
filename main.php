<?php
/*
 * Menetrendmahináló v0.3 beta
 * Írta: Keleti Márton (K.Marci)
 * E-mail: kmarton@vipmail.hu
 *
 * Ez az alkotás a Creative Commons Nevezd meg! - Ne add el! - Ne változtasd! 2.5 Magyarország licenc alá tartozik.
 * A licenc megtekintéséhez látogass el a http://creativecommons.org/licenses/by-nc-nd/2.5/hu/ oldalra,
 * vagy lásd a mellékelt LICENC.txt állományt.
 */

setlocale(LC_ALL, "");
define("CHARSET", "UTF-8");
define("FILESYSTEM_CHARSET", "CP1250");
define("CMD_CHARSET", "CP852");
define("VERSION", "v0.3 beta");

define("ABOUT",
'Menetrendmahináló '.VERSION.'
Írta: Keleti Márton (K.Marci)
E-mail: kmarton@vipmail.hu

Ez az alkotás a Creative Commons Nevezd meg! - Ne add el! - Ne változtasd! 2.5 Magyarország licenc alá tartozik.
A licenc megtekintéséhez látogass el a
<a href="http://creativecommons.org/licenses/by-nc-nd/2.5/hu/">http://creativecommons.org/licenses/by-nc-nd/2.5/hu/</a>
oldalra, vagy lásd a mellékelt LICENC.txt állományt.

This product includes PHP software, freely available from
&lt;<a href="http://www.php.net/software/">http://www.php.net/software/</a>&gt;');

define("HELP",
'Biztonsági másolat csak a bepipálást követő első mentés előtt készül
Járat menetrendjének módosítása két gyors kattintással
Időpontok módosítása két lassabb kattintással
Activity kezdésének módosítása a Játékos kezdésének módosításával
Tipp: , vagy . is használható : helyett, így gyorsan lehet numerikus billentyűzettel dolgozni
Tipp2: egyszerű aritmetikai műveletek használhatók: +1 bevitele egy másodpercet, ++1 egy percet, +++1 egy órát ad hozzá, értelemszerűen ugyanígy működik a -');

function popup($message = "", $type = 'info', $waitForResponse = true, $borders = true, $buttons = Gtk::BUTTONS_OK, GtkWindow $wnd = NULL) {
//functionVersion: 1
    switch(strtolower($type)) {
        case 'error':
            $type = Gtk::MESSAGE_ERROR;
            break;
        case 'warning':
            $type = Gtk::MESSAGE_WARNING;
            break;
        case 'info':
            $type = Gtk::MESSAGE_INFO;
            break;
        case 'question':
            $type = Gtk::MESSAGE_QUESTION;
            break;
        default:
            $type = Gtk::MESSAGE_OTHER;
            break;
    }
    if(!$wnd)
        $wnd = $GLOBALS['wnd'];
    if(is_array($buttons)) {
        $dialog = new GtkMessageDialog($wnd, Gtk::DIALOG_MODAL, $type, Gtk::BUTTONS_NONE, ""); //üres string üzenetként, mert így nem venné be a formázást
        $dialog->add_buttons($buttons);
    }
    else
        $dialog = new GtkMessageDialog($wnd, Gtk::DIALOG_MODAL, $type, $buttons, "");
    $dialog->set_markup($message);
    $dialog->set_position(Gtk::WIN_POS_CENTER_ON_PARENT);
    $borders ? $dialog->set_type_hint(Gdk::WINDOW_TYPE_HINT_MENU) : $dialog->set_decorated(false);
    //$dialog->set_decorated($borders);
    //$dialog->set_deletable(false);

    if($waitForResponse) {
        $resp = $dialog->run();
        $dialog->destroy();
        $wnd->present();
    }
    else {
        $dialog->show_all();
        Gtk::main_iteration();
        /*$child = $dialog->get_child();
        $vboxc = $child->get_children();
        $buttonboxC = $vboxc[1]->get_children();
        $button = $buttonboxC[0];
        $button->connect_simple("clicked", array($dialog, "destroy"));*/

        $resp = $dialog;
    }
    return $resp;
}

function exceptionHandler(Exception $e) {
    popup("Nem kezelt kivétel történt: ".$e->getMessage()."\r\nStack trace:\r\n".$e->getTraceAsString(), "error");
}

function readTrf($filename) {
    $fp = fopen(realpath($filename), "r");
    $sor = fgets($fp); //SIMISA kihagyása
    $trf['srv'] = false;
    while(!feof($fp)) {
        $sor = substr(fgets($fp), 1)."\x0"; //fgets az újsor közepén vágja el, ezért kell így összerakni...
        $sor = trim(@iconv("UCS-2LE", CHARSET."//TRANSLIT", $sor));
        if(!$sor)
            continue;
        $tok = strtok($sor, " ");
        switch($tok) {
            case "Traffic_Definition":
                $trf['name'] = trim(substr($sor, 21), '"');
                break;
            case "Serial":
                $trf['serial'] = trim(substr($sor, 9), " )");
                break;
            case "Service_Definition":
                preg_match('~Service_Definition \( "?(.+)"? ([0-9]+) ?\)?~', $sor, $matches);
                $srv = trim($matches[1], '"');
                $trf['con'][$srv] = getConData($srv);
                $trf['srv'][$srv]['start'] = $matches[2];
                $station = 0;
                break;
            case "ArrivalTime":
                $station++;
            case "DepartTime":
            case "SkipCount":
            case "DistanceDownPath":
            case "PlatformStartID":
                preg_match('~\( ([0-9.]+) \)~', $sor, $matches);
                $trf['srv'][$srv][$station][$tok] = $matches[1];
                break;
        }
    }

/*    $tab = 0;
    foreach($file as $n => $sor) {
        $sor = trim($sor);
        if($sor && substr_count($sor, "(") > substr_count($sor, ")"))
            $file[$n] = str_repeat("\t", $tab++) . $sor;
        else if($sor == ")")
            $file[$n] = str_repeat("\t", --$tab) . $sor;
        else
            $file[$n] = str_repeat("\t", $tab) . $sor;
    }  */
    return $trf;
}

function readTit($filename) {
    $trace = false;
    $fp = fopen(realpath($filename), "r");
    $sor = fgets($fp); //SIMISA kihagyása
    while(!feof($fp)) {
        $sor = substr(fgets($fp), 1)."\x0"; //fgets az újsor közepén vágja el, ezért kell így összerakni...
        $sor = trim(@iconv("UCS-2LE", CHARSET."//TRANSLIT", $sor));
        if(!$sor)
            continue;
        $tok = strtok($sor, " ");
        switch($tok) {
            case "PlatformItem":
                $trace = true;
                break;
            case "TrItemId":
                if($trace) {
                    preg_match('~\( ([0-9.]+) \)~', $sor, $matches);
                    $id = $matches[1];
                }
                break;
            case "PlatformName":
                if($trace) {
                    preg_match('~\( "?(.+)"? \)~', $sor, $matches);
                    $tit[$id] = trim($matches[1], '"');
                    $trace = false;
                }
                break;
        }
    }
    fclose($fp);
    return $tit;
}

function readAct($filename) {
    $fp = fopen(realpath($filename), "r");
    $sor = fgets($fp); //SIMISA kihagyása
    $rest1 = false;
    $head = false;
    $desbri = false;
    while(!feof($fp)) {
        $sor = substr(fgets($fp), 1)."\x0"; //fgets az újsor közepén vágja el, ezért kell így összerakni...
        $sor = @iconv("UCS-2LE", CHARSET."//TRANSLIT", $sor);
        if($rest1) {
            $act['rest1'][] = $sor;
            if(preg_match('~Traffic_Definition \( (.+)~', $sor, $matches)) {
                $act['file']['traffic'] = trim($matches[1], "\"\r\n )");
            }
            continue;
        }
        $sor = trim($sor);
        if(!$sor)
            continue;
        $tok = strtok($sor, " ");
        switch($tok) {
            case "Serial":
                preg_match('~Serial \( ([0-9]+) \)~', $sor, $matches);
                $act['serial'] = $matches[1];
                break;
            case "Tr_Activity_Header":
                $head = true;
                break;
            case "Tr_Activity_File":
                $head = false;
                break;
            case "Player_Service_Definition":
                preg_match('~Player_Service_Definition \( (.+)~', $sor, $matches);
                $act['file']['player_service']['name'] = trim($matches[1], '"');
                break;
            case "Player_Traffic_Definition":
                preg_match('~Player_Traffic_Definition \( ([0-9]+)~', $sor, $matches);
                $act['file']['player_service']['player_traffic']['start'] = $matches[1];
                $station = 0;
                break;
            case "ArrivalTime":
                $station++;
            case "DepartTime":
            case "SkipCount":
            case "DistanceDownPath":
            case "PlatformStartID":
                preg_match('~\( ([0-9.]+) \)~', $sor, $matches);
                $act['file']['player_service']['player_traffic'][$station][$tok] = $matches[1];
                break;
            case "UiD":
                preg_match('~UiD \( ([0-9]+) \)~', $sor, $matches);
                $act['file']['player_service']['uid'] = $matches[1];
                //MINDEN EGYÉB:
                $rest1 = true;
                break;
            case "Description":            //TODO
            case "Briefing":
                if(preg_match("~$tok \( \"(.*)\"\\+$~", $sor, $matches)) {
                    $desbri = $tok;
                    $act['head'][$tok][] = $matches[1];
                    break;
                }
                echo null;
            default:
                if($desbri) {
                    /*  0=>"\"Készítette: sony1986pali\\n\"+"
                        1=>"\"Készítette: sony1986pali\\n\""
                        2=>"Készítette: sony1986pali\\n"
                        3=>"+" VAGY ")" VAGY ""
                    */
                    preg_match('~("(.*)")? *(\+|\)|)~', $sor, $matches);
                    $act['head'][$desbri][] = $matches[2];
                    if($matches[3] == ")" || $matches[3] == "")
                        $desbri = false;
                    //var_dump($matches);
                    break;
                }
                if($head) {
                    if($sor == ')')
                        break;
                    preg_match('~(.+) \( (.+) \)~', $sor, $matches);
                    $act['head'][$matches[1]] = trim($matches[2], '"');
                }
                break;
        }
    }
    return $act;
}

function stfParser($filename) {  //elegánsabb lenne a fentieknél...
    $fp = fopen(realpath(iconv(CHARSET, FILESYSTEM_CHARSET, $filename)), "r");
    $simisa = trim(@iconv("UCS-2LE", CHARSET, substr(fgets($fp), 2)));
    $raw = array();
    while(!feof($fp)) {
        $sor = substr(fgets($fp), 1)."\x0"; //fgets az újsor közepén vágja el, ezért kell így összerakni...
        $sor = trim(@iconv("UCS-2LE", CHARSET."//TRANSLIT", $sor));
        if(!$sor)
            continue;
        /*$tok = strtok($sor, " ");
        switch($tok) {
            case "(":

            default:
                $stf[] = null;
                break;
        }*/
        $raw = array_merge($raw, explode(" ", $sor));
    }
    fclose($fp);
    $type = 'var';
    foreach($raw as $item) {
        switch($type) {
            case 'var':
                $var = $item;
                $type = 'value';
                break;
            case 'value':
                $stf[$var] = $item;
        }
    }
    return $stf;
}

function stfNode($node) {
    if(!isset($node[1]))
        return $node[0];
    if($node[1] === "(") {
        $var = $node[0];
        unset($node[0], $node[1], $node[count($node)-1]);
        return array($var => stfNode($node));
    }
}

function writeUnicodeFile($fp, $str, $close = false) {
    $ret = fwrite($fp, iconv(CHARSET, "UCS-2LE", $str));
    if($close)
        fclose($fp);
    return $ret;
}

function writeTrf($filename, $trf) {
    $fp = fopen(realpath($filename), "w");
    fwrite($fp, "\xff\xfe"); //DOM
    writeUnicodeFile($fp, "SIMISA@@@@@@@@@@JINX0f0t______\r\n");
    writeUnicodeFile($fp, "\r\n");
    writeUnicodeFile($fp, strpos($trf['name'], " ") !== false ? "Traffic_Definition ( \"$trf[name]\"\r\n" : "Traffic_Definition ( $trf[name]\r\n");
    writeUnicodeFile($fp, "\tSerial ( $trf[serial] )\r\n");
    foreach($trf['srv'] as $srv => $dat) {
        if(strpos($srv, " ") !== false)
            writeUnicodeFile($fp, "\tService_Definition ( \"$srv\" $dat[start]");
        else
            writeUnicodeFile($fp, "\tService_Definition ( $srv $dat[start]");
        if(!isset($dat[1])) {
            writeUnicodeFile($fp, " )\r\n");
            continue;
        }
        writeUnicodeFile($fp, "\r\n");
        unset($dat['start']);
        foreach($dat as $station) {
            foreach($station as $var => $val) {
                writeUnicodeFile($fp, "\t\t$var ( $val )\r\n");
            }
        }
        writeUnicodeFile($fp, "\t)\r\n");
    }
    writeUnicodeFile($fp, ")");
    fclose($fp);
}

function writeAct($filename, $act, $trf) {
    $fp = fopen(realpath($filename), "w");
    fwrite($fp, "\xff\xfe"); //DOM
    writeUnicodeFile($fp, "SIMISA@@@@@@@@@@JINX0a0t______\r\n");
    writeUnicodeFile($fp, "\r\n");
    writeUnicodeFile($fp, "Tr_Activity (\r\n");
    writeUnicodeFile($fp, "\tSerial ( {$act['serial']} )\r\n");
    writeUnicodeFile($fp, "\tTr_Activity_Header (\r\n");
    foreach($act['head'] as $var => $val) {
        if(is_array($val)) {
            writeUnicodeFile($fp, "\t\t$var ( \"$val[0]\"");
            unset($val[0]);
            foreach($val as $line) {
                writeUnicodeFile($fp, "+\r\n\t\t\t \"$line\"");
            }
            writeUnicodeFile($fp, " )\r\n");
            continue;
        }
        writeUnicodeFile($fp, "\t\t$var ( ". ($var == "StartTime" || $var == "Duration" ? $val : quote($val)) ." )\r\n");
    }
    writeUnicodeFile($fp, "\t)\r\n");
    writeUnicodeFile($fp, "\tTr_Activity_File (\r\n");
    writeUnicodeFile($fp, "\t\tPlayer_Service_Definition ( ".quote($act['file']['player_service']['name'])."\r\n");
    writeUnicodeFile($fp, "\t\t\tPlayer_Traffic_Definition ( ".quote($act['file']['player_service']['player_traffic']['start'])."\r\n");
    unset($act['file']['player_service']['player_traffic']['start']);
    foreach($act['file']['player_service']['player_traffic'] as $station) {
        foreach($station as $var => $val) {
            writeUnicodeFile($fp, "\t\t\t\t$var ( ".quote($val)." )\r\n");
        }
    }
    writeUnicodeFile($fp, "\t\t\t)\r\n");
    writeUnicodeFile($fp, "\t\t\tUiD ( {$act['file']['player_service']['uid']} )\r\n");
    foreach($act['rest1'] as $sor) {
        if(preg_match('~Service_Definition \( "?(.+)"? ([0-9]+) ?\)?~', $sor, $matches)) {
            $sor = preg_replace('~[0-9]+$~', $trf['srv'][$matches[1]]['start'], $sor);
        }
        writeUnicodeFile($fp, $sor);
    }
    fclose($fp);
}

function getConData($srv_name) {
    $act_dir = dirname($GLOBALS['act_path']);
    $srv_file = iconv("UCS-2LE", CHARSET, file_get_contents(iconv(CHARSET, FILESYSTEM_CHARSET, path("$act_dir\\..\\SERVICES\\$srv_name.srv"))));
    if(!$srv_file) {
        $message = path("$act_dir\\..\\SERVICES\\$srv_name.srv")." nem található!";
        writeLog($message);
        popup($message, "error");
    }
    preg_match('~Train_Config \( (.+) \)\r\n~i', $srv_file, $matches);
    $matches[1] = trim($matches[1], '" ');
    $con_path = path("$act_dir\\..\\..\\..\\TRAINS\\CONSISTS\\$matches[1].con");
    $con_file = iconv("UCS-2LE", CHARSET, file_get_contents(iconv(CHARSET, FILESYSTEM_CHARSET, $con_path)));
    preg_match('~MaxVelocity \( ([0-9.]+) ([0-9.]+) \)\r\n~i', $con_file, $matches);
    return array("con_file" => $con_file, "con_path" => $con_path, "A" => $matches[1], "B" => $matches[2], "modified" => false);
}

function quote($str) {
    return strpos($str, " ") !== false ? '"'.$str.'"' : $str;
}

function path($path) {
    $path = str_replace("/", "\\", $path);
    $parts = explode("\\", $path);
    $ret = array();
    $i = 0;
    foreach($parts as $part) {
        if($part === "..") {
            unset($ret[--$i]);
        }
        else {
            $ret[$i++] = $part;
        }
    }
    $ret = implode("\\", $ret);
    return $ret;
}

function timeConv($in) {
    if(is_numeric($in)) {
        $sec = $in % 60;
        $min = ($in-$sec) % 3600 / 60;
        $hour = floor($in / 3600);
        if($hour<10)
            $hour = "0".$hour;
        if($min<10)
            $min = "0".$min;
        if($sec<10)
            $sec = "0".$sec;
        return "$hour:$min:$sec";
    }
    @list($hour, $min, $sec) = explode(":", $in);
    return (string)($hour*3600 + $min*60 + $sec);
}

function writeLog($str = "") {
    if($str === "")
        return;
    echo iconv(CHARSET, CMD_CHARSET."//TRANSLIT", $str).PHP_EOL;
}

/////////////////////////
/////////////////////////

function load() {
    global $folder, $activity, $bizt, $model, $act, $trf, $tit, $frame1, $frame2, $act_path, $trf_path;
    static $tit_path;
    if(!$act_path = $activity->get_filename()) {
        popup("Válassz egy activityt!", "error");
        return true;
    }
    writeLog("Activity $act_path olvasása");
    $act = readAct(iconv(CHARSET, FILESYSTEM_CHARSET, $act_path));
    $act['consist'] = getConData($act['file']['player_service']['name']);
    writeLog("Activity beolvasva");
    if(isset($act['file']['traffic'])) {
        $trf_path = path(dirname($act_path).'/../TRAFFIC/'.$act['file']['traffic'].'.trf');
        writeLog("Traffic $trf_path olvasása");
        $trf = readTrf(iconv(CHARSET, FILESYSTEM_CHARSET, $trf_path));
        writeLog("Traffic beolvasva");
    }
    else {
        $trf = $trf_path = false;
    }
    $new_tit_path = path(dirname($act_path).'/../'.$act['head']['RouteID'].'.tit');
    if($new_tit_path !== $tit_path) {
        writeLog("Tit $new_tit_path olvasása");
        $tit = readTit(iconv(CHARSET, FILESYSTEM_CHARSET, $new_tit_path));
        writeLog("Tit beolvasva");
        $tit_path = $new_tit_path;
    }
    /*if($bizt->get_active()) {
        file_put_contents($act_path.'.bak', file_get_contents($act_path));
        file_put_contents($trf_path.'.bak', file_get_contents($trf_path));
    }*/
    //getConData($act['file']['player_service']['name']);
    $model->clear();
    $model->append(array("Játékos ({$act['file']['player_service']['name']})",
                         timeconv($act['file']['player_service']['player_traffic']['start']),
                         round($act['consist']['A']*3.6, 0)));
    if(is_array($trf['srv'])) {
        foreach($trf['srv'] as $name => $srv) {
            $model->append(array($name, timeConv($srv['start']), round($trf['con'][$name]['A']*3.6, 0)));
        }
    }
    $frame1->set_label(basename($act_path)." | ".basename($trf_path));
    $GLOBALS['button2']->set_sensitive(true);
    //$dialog->destroy();
    //$GLOBALS['wnd']->set_position(Gtk::WIN_POS_CENTER);
}

function load_srv() {
    global $selection, $act, $trf, $tit, $model2, $frame2, $srv_name, $wnd;
    list($model, $iter) = $selection->get_selected();
    //echo $model->get_string_from_iter($iter);
    $srv_name = $model->get_value($iter, 0);
    $frame2->set_label($srv_name);
    $model2->clear();
    if(preg_match('~Játékos \(.+\)~', $srv_name)) {
        $traffic = $act['file']['player_service']['player_traffic'];
    }
    else {
        $traffic = $trf['srv'][$srv_name];
    }
    $start = $traffic['start'];
    unset($traffic['start']);
    foreach($traffic as $station) {
        $nev = $tit[$station['PlatformStartID']];
        $erk = $station['ArrivalTime'];
        $ind = $station['DepartTime'];
        $model2->append(array($nev, timeConv($erk), timeConv($ind)));
    }
    $GLOBALS['button3']->set_sensitive(true);
    Gtk::timeout_add(10, array($frame2, 'queue_draw'));
}

function edit_srv(GtkCellRendererText $renderer, $sor, $val) {
    global $model2, $renderer2_2, $renderer2_3, $act, $trf, $srv_name, $tit;
    $player = preg_match('~Játékos \(.+\)~', $srv_name);
    $iter = $model2->get_iter_from_string($sor);
    $col = $renderer === $renderer2_2 ? 1 : 2;
    $current = $model2->get_value($iter, $col);
    if($current == $val)
        return;
    $val = edit_time($current, $val);
    if($val === false)
        return;
    $model2->set($iter, $col, $val);
    if($player) {
        $traffic = &$act['file']['player_service']['player_traffic'];
    }
    else {
        $traffic = &$trf['srv'][$srv_name];
    }
    $sor++;
    if($col == 1) {
        $traffic[$sor]['ArrivalTime'] = timeConv($val);
        writeLog($srv_name." járat ".$tit[$traffic[$sor]['PlatformStartID']]. " állomásra érkezése: $current -> $val");
        if($traffic[$sor]['ArrivalTime'] > $traffic[$sor]['DepartTime']) {
            $oldDepart = timeConv($traffic[$sor]['DepartTime']);
            $traffic[$sor]['DepartTime'] = $traffic[$sor]['ArrivalTime'];
            $model2->set($iter, 2, $val);
            writeLog($srv_name." járat ".$tit[$traffic[$sor]['PlatformStartID']]. " állomásról indulása: $oldDepart -> $val");
        }

    }
    else {
        $traffic[$sor]['DepartTime'] = timeConv($val);
        writeLog($srv_name." járat ".$tit[$traffic[$sor]['PlatformStartID']]. " állomásról indulása: $current -> $val");
    }
}

function edit_act(GtkCellRendererText $renderer, $sor, $val) {
    global $model, $act, $trf;
    $iter = $model->get_iter_from_string($sor);
    $current = $model->get_value($iter, 1);
    if($current == $val)
        return;
    $val = edit_time($current, $val);
    if($val === false)
        return;
    $model->set($iter, 1, $val);
    if($sor === "0") { //játékos
        $traffic = &$act['file']['player_service']['player_traffic'];
        $act['head']['StartTime'] = str_replace(":", " ", $val);  //activity kezdésének módosítása egyúttal
    }
    else
        $traffic = &$trf['srv'][$model->get_value($iter, 0)];
    $traffic['start'] = timeConv($val);
    writeLog($model->get_value($iter, 0)." járat kezdési ideje: $current -> $val");
}

function edit_vmax(GtkCellRendererText $renderer, $sor, $val) {
    //TODO
    global $model, $act, $trf;
    $iter = $model->get_iter_from_string($sor);
    $current = $model->get_value($iter, 2);
    if($current == $val)
        return;
    if($val[0] === "+")
        $val = $current + @substr($val, 1);
    else if($val[0] === "-")
        $val = $current - @substr($val, 1);
    $model->set($iter, 2, (string)$val);
    if($sor === "0") { //játékos
        $con = &$act['consist'];
    }
    else {
        $con = &$trf['con'][$model->get_value($iter, 0)];
    }
    $old_A = $con['A'];
    $con['A'] = str_replace(",", ".", (string)round($val / 3.6, 5));
    //$repl = '${1}'.$con['A'];
    //$con['con_file'] = preg_replace('~MaxVelocity \( ([0-9.]+) [0-9.]+ \)~i', $repl, $con['con_file']);
    $con['con_file'] = str_replace("MaxVelocity ( $old_A $con[B] )", "MaxVelocity ( $con[A] $con[B] )", $con['con_file']);
    $con['modified'] = true;
    writeLog($con['con_path']." összeállítás maximális sebessége módosítva: $old_A => $con[A] m/s");
}

function edit_time($current, $val) {
    if(is_numeric($val))
        $val .= ",";
    $current = timeConv($current);
    $val = str_replace(",", ".", $val);
    {
        if($val[2] === "+") {
            $val = timeConv($current + substr($val, 3) * 3600);
        }
        if($val[1] === "+") {
            $val = timeConv($current + substr($val, 2) * 60);
        }
        if($val[0] === "+") {
            $val = timeConv($current + substr($val, 1));
        }
        if($val[2] === "-") {
            $val = timeConv($current - substr($val, 3) * 3600);
        }
        if($val[1] === "-") {
            $val = timeConv($current - substr($val, 2) * 60);
        }
        if($val[0] === "-") {
            $val = timeConv($current - substr($val, 1));
        }
    }
    $val = str_replace(".", ":", $val);
    if(!preg_match('~^[0-9]{1,2}:?[0-9]{0,2}:?[0-9]{0,2}$~', $val)) {
        popup("Syntax error", "error");
        return false;
    }
    $val = timeConv(timeConv($val)); //így lesz 5:23-ból 5:23:0
    return $val;
}

function save() {
    global $act_path, $trf_path, $act, $trf, $bizt;
    static $bak = true;

    writeLog("Mentés...");
    if($bak && $bizt->get_active()) {
        writeLog("Biztonsági másolatok készítése");
        file_put_contents(iconv(CHARSET, FILESYSTEM_CHARSET, $act_path).'.bak', file_get_contents(iconv(CHARSET, FILESYSTEM_CHARSET, $act_path)));
        if($trf)
            file_put_contents(iconv(CHARSET, FILESYSTEM_CHARSET, $trf_path).'.bak', file_get_contents(iconv(CHARSET, FILESYSTEM_CHARSET, $trf_path)));
        $bak = false;
    }
    writeLog("$act_path írása");
    writeAct(iconv(CHARSET, FILESYSTEM_CHARSET, $act_path), $act, $trf);
    writeLog("Activity mentve");
    if($trf) {
        writeLog("$trf_path írása");
        writeTrf(iconv(CHARSET, FILESYSTEM_CHARSET, $trf_path), $trf);
        writeLog("Traffic mentve");
    }
    writeLog("Összeállítások mentése...");
    if($act['consist']['modified']){
        writeLog($act['consist']['con_path']);
        writeUnicodeFile(fopen($act['consist']['con_path'], "w"), $act['consist']['con_file'], true);
    }
    if($trf) {
        foreach($trf['con'] as $con) {
            if($con['modified']) {
                writeLog($con['con_path']);
                writeUnicodeFile(fopen(iconv(CHARSET, FILESYSTEM_CHARSET, $con['con_path']), "w"), $con['con_file'], true);
            }
        }
    }
    popup("Mentve", "info");
}

function erk() {
    global $act, $trf, $srv_name, $model2, $combo1, $combo2, $combo3, $hentry, $mentry, $sentry;
    $player = preg_match('~Játékos \(.+\)~', $srv_name);
    if($player) {
        $traffic = &$act['file']['player_service']['player_traffic'];
    }
    else {
        $traffic = &$trf['srv'][$srv_name];
    }
    $h = (int)$hentry->get_text(); $m = (int)$mentry->get_text(); $s = (int)$sentry->get_text();
    $mennyit = timeConv("$h:$m:$s");
    $mit = $combo1->get_active();
    $hogyan = $combo3->get_active(); //0 = +   1 = -
    writeLog("$srv_name járat összes ".$combo1->get_active_text()." = ".$combo2->get_active_text()." ".$combo3->get_active_text()." $h:$m:$s");
    foreach($traffic as $sor => $station) {
        if(!is_array($station))
            continue;
        $honnan = $combo2->get_active() ? $traffic[$sor]['DepartTime'] : $traffic[$sor]['ArrivalTime']; //0=érk 1=ind
        $iter = $model2->get_iter_from_string($sor-1);
        if($mit) { //indulást módosítunk
            $traffic[$sor]['DepartTime'] = (string)($hogyan ? $honnan - $mennyit : $honnan + $mennyit);
            $model2->set($iter, 2, timeConv($traffic[$sor]['DepartTime']));
        }
        else {  //érkezést módosítunk
            $traffic[$sor]['ArrivalTime'] = (string)($hogyan ? $honnan - $mennyit : $honnan + $mennyit);
            $model2->set($iter, 1, timeConv($traffic[$sor]['ArrivalTime']));
        }
    }
}

/////////////////////////
/////////////////////////

set_exception_handler("exceptionHandler");
writeLog(html_entity_decode(strip_tags(ABOUT)));

$shell = new COM('WScript.Shell');
try {
    $default = $shell->regRead('HKEY_LOCAL_MACHINE\SOFTWARE\Microsoft\Microsoft Games\Train Simulator\1.0\Path');
}
catch (Exception $e) {
    $default = iconv(FILESYSTEM_CHARSET, CHARSET, __DIR__);
    unset($e);
}
unset($shell);

$wnd = new GtkWindow();
$mainbox = new GtkVBox();

$menubar = new GtkMenuBar();
$menuitem = new GtkMenuItem('_Súgó');
$helpmenu = new GtkMenu();
$help = new GtkImageMenuItem("Használat");
$about = new GtkImageMenuItem("Névjegy");
$menuitem2 = new GtkMenuItem('_Eszközök');
$toolsmenu = new GtkMenu();


$aboutDialog = popup(ABOUT, "info", false);
$aboutDialog->hide_all();
$aboutDialog->connect_simple("response", array($aboutDialog, "hide_all"));
$aboutDialog->set_title("Névjegy");
$helpDialog = popup(HELP, "info", false);
$helpDialog->hide_all();
$helpDialog->connect_simple("response", array($helpDialog, "hide_all"));
$helpDialog->set_title("Használat");
$menubar->add($menuitem);
$menuitem->set_submenu($helpmenu);
$helpmenu->append($help);
$helpmenu->append($about);
$help->set_image(GtkImage::new_from_stock(Gtk::STOCK_HELP, Gtk::ICON_SIZE_MENU));
$about->connect_simple('activate', array($aboutDialog, "show_all"));
$help->connect_simple('activate', array($helpDialog, "show_all"));

$table = new GtkTable();
$label1 = new GtkLabel("MSTS");
$label2 = new GtkLabel("Activity");
$label3 = new GtkLabel("Biztonsági másolat");
$folder = new GtkFileChooserButton("Add meg az MSTS helyét!", Gtk::FILE_CHOOSER_ACTION_SELECT_FOLDER);
$activity = new GtkFileChooserButton("Add meg az Activity helyét!", Gtk::FILE_CHOOSER_ACTION_OPEN);
$filter = new GtkFileFilter();
$bizt = new GtkCheckButton("Biztonsági másolat készítése");
$buttonbox = new GtkHButtonBox();
$button = new GtkButton("Betölt");
$button2 = new GtkButton("Ment");
$button3 = new GtkButton("Mehet");
$frame1 = new GtkFrame();
$frame2 = new GtkFrame();

$hbox = new GtkHBox();
$combo1 = GtkComboBox::new_text();
$combo1->append_text("Érkezés");
$combo1->append_text("Indulás");
$combo1->set_active(0);
$combo2 = GtkComboBox::new_text();
$combo2->append_text("Érkezés");
$combo2->append_text("Indulás");
$combo2->set_active(0);
$combo3 = GtkComboBox::new_text();
$combo3->append_text("+");
$combo3->append_text("-");
$combo3->set_active(0);
$hentry = new GtkEntry();
$hentry->set_max_length(2);
$hentry->set_width_chars(2);
$mentry = new GtkEntry();
$mentry->set_max_length(2);
$mentry->set_width_chars(2);
$sentry = new GtkEntry();
$sentry->set_max_length(2);
$sentry->set_width_chars(2);
$hbox->pack_start($combo1);
$hbox->pack_start(new GtkLabel("="));
$hbox->pack_start($combo2);
$hbox->pack_start($combo3);
$hbox->pack_start($hentry);
$hbox->pack_start(new GtkLabel(":"));
$hbox->pack_start($mentry);
$hbox->pack_start(new GtkLabel(":"));
$hbox->pack_start($sentry);
$hbox->pack_start($button3);



$model = new GtkListStore(64, 64, 32);  // GObject::TYPE_STRING === (int)64           GObject::TYPE_LONG === (int)32
$view1 = new GtkTreeView($model);
$scroll1 = new GtkScrolledWindow();
$col1 = new GtkTreeViewColumn("Járat");
$col2 = new GtkTreeViewColumn("Kezdés");
$col3 = new GtkTreeViewColumn("Vmax");
$view1->append_column($col1);
$view1->append_column($col2);
$view1->append_column($col3);
$renderer1 = new GtkCellRendererText();
$renderer2 = new GtkCellRendererText();
$renderer3 = new GtkCellRendererText();
$col1->pack_start($renderer1, true);
$col2->pack_start($renderer2, true);
$col3->pack_start($renderer3, true);
$col1->set_attributes($renderer1, 'text', 0);
$col2->set_attributes($renderer2, 'text', 1);
$col3->set_attributes($renderer3, 'text', 2);
$renderer2->set_property("editable", true);
$renderer2->connect("edited", "edit_act");
$renderer3->set_property("editable", true);
$renderer3->connect("edited", "edit_vmax");
$selection = $view1->get_selection();
$view1->connect_simple("row-activated", "load_srv");
$scroll1->add($view1);
$scroll1->set_policy(Gtk::POLICY_NEVER, Gtk::POLICY_AUTOMATIC);



$model2 = new GtkListStore(64, 64, 64);  // GObject::TYPE_STRING === (int)64
$view2 = new GtkTreeView($model2);
$scroll2 = new GtkScrolledWindow();
$col2_1 = new GtkTreeViewColumn("Állomás");
$col2_2 = new GtkTreeViewColumn("Érkezés");
$col2_3 = new GtkTreeViewColumn("Indulás");
$view2->append_column($col2_1);
$view2->append_column($col2_2);
$view2->append_column($col2_3);
$renderer2_1 = new GtkCellRendererText();
$renderer2_2 = new GtkCellRendererText();
$renderer2_3 = new GtkCellRendererText();
$col2_1->pack_start($renderer2_1, true);
$col2_2->pack_start($renderer2_2, true);
$col2_3->pack_start($renderer2_3, true);
$col2_1->set_attributes($renderer2_1, 'text', 0);
$col2_2->set_attributes($renderer2_2, 'text', 1);
$col2_3->set_attributes($renderer2_3, 'text', 2);
$renderer2_2->set_property("editable", true);
$renderer2_3->set_property("editable", true);
$renderer2_2->connect("edited", "edit_srv");
$renderer2_3->connect("edited", "edit_srv");
$scroll2->add($view2);
$scroll2->set_policy(Gtk::POLICY_NEVER, Gtk::POLICY_AUTOMATIC);


$buttonbox->set_layout(Gtk::BUTTONBOX_SPREAD);
$buttonbox->pack_start($button);
$buttonbox->pack_start($button2);
$button->connect('clicked', 'load');
$button->connect_simple('clicked', array($frame2, 'set_label'), "Nincs járat kijelölve");
$button->connect_simple('clicked', array($model2, 'clear'));
$button2->set_sensitive(false);
$button3->set_sensitive(false);
$button2->connect('clicked', 'save');
$button3->connect_simple('clicked', 'erk');

$frame1->add($scroll1);
$frame2->add($scroll2);
$frame1->set_label("Nincs activity betöltve");
$frame2->set_label("Nincs járat kijelölve");
$frame1->set_size_request(250, 0);

$hpane = new GtkHPaned();
$hpane->add1($frame1);
$hpane->add2($frame2);

/*$table->attach($label1, 0, 1, 0, 1);
$table->attach($folder, 1, 2, 0, 1); */
$table->attach($label2, 0, 1, 1, 2, Gtk::SHRINK, Gtk::SHRINK);
$table->attach($activity, 1, 2, 1, 2, Gtk::EXPAND | GTK::FILL, Gtk::SHRINK);
//$table->attach($label3, 0, 1, 2, 3, Gtk::SHRINK, Gtk::SHRINK);
$table->attach($bizt, 2, 3, 1, 2, Gtk::SHRINK, Gtk::SHRINK);
$table->attach($buttonbox, 0, 3, 3, 4, Gtk::EXPAND | GTK::FILL, Gtk::SHRINK);
$table->attach($hbox, 0, 3, 4, 5, Gtk::EXPAND | GTK::FILL, Gtk::SHRINK);
/*$table->attach($frame1, 0, 1, 5, 6);
$table->attach($frame2, 1, 2, 5, 6);  */
$table->attach($hpane, 0, 3, 5, 6);
$table->set_homogeneous(false);

$mainbox->pack_start($menubar, false, false);
$mainbox->pack_start($table, true, true);

$filter->add_pattern("*.act");
$filter->set_name("MSTS Activity fájlok (*.act)");
$activity->add_filter($filter);
$folder->set_current_folder($default);
$activity->set_current_folder($default.'\ROUTES');

//$activity->set_filename('d:\Program Files\Microsoft Games\Train Simulator\ROUTES\Alfold_6\ACTIVITIES\a5-17605-acthu.act');

$wnd->add($mainbox);
$wnd->connect_simple("destroy", array("Gtk", "main_quit"));
//$wnd->set_position(Gtk::WIN_POS_CENTER_ALWAYS);
//$wnd->set_resizable(false);
$wnd->set_size_request(540, 430);
$wnd->set_title("Menetrendmahináló ".VERSION);
$wnd->show_all();
Gtk::Main();
?>