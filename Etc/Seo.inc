<?php


function create_filename_from_text( $title )
{
    # first remove all special slovenian, croatian, ukrainian, etc. characters from title
    $title = sumniki($title);

    # put title to lowercase
    $title = strtolower($title);

    # leave only letters, numbers, and inbetween a single occurence of dash
    $pattern = array(
	"/\s\s*/", # 1. replace spaces with dashes
	"/[^a-z0-9]/", # 2. replace special characters with dashes
	"/--*/", # 3. remove multiple daskes
	"/^--*/", # 4. remove dashes at begining
	"/--*$/"    # 5. remove dashes at end
    );

    $replace = array(
	"-", # 1.
	"-", # 2.
	"-", # 3.
	"", # 4.
	"", # 5.
    );

    $title = preg_replace($pattern, $replace, $title);

    return $title;
}


////
//!Changes all slovenian chars to us-ascii ones
//@author Samo Gabrovec (root@velenje.cx)
//@param string
//@return string[us-ascii]
//@globs   none
function sumniki( $string )
{

    // CZ alphabet
    $string = strtr($string, array(
	'á' => 'a',
	'ä' => 'a',
	'Á' => 'A',
	'Ä' => 'A',
	'č' => 'c',
	'Č' => 'C',
	'ď' => 'd',
	'Ď' => 'D',
	'é' => 'e',
	'ě' => 'e',
	'É' => 'E',
	'Ě' => 'E',
	'í' => 'i',
	'Í' => 'I',
	'ľ' => 'l',
	'Ľ' => 'L',
	'ň' => 'n',
	'Ň' => 'N',
	'ó' => 'o',
	'ô' => 'o',
	'Ó' => 'O',
	'Ô' => 'O',
	'ř' => 'r',
	'ŕ' => 'r',
	'Ř' => 'R',
	'Ŕ' => 'R',
	'š' => 's',
	'Š' => 'S',
	'ť' => 't',
	'Ť' => 'T',
	'ú' => 'u',
	'ů' => 'u',
	'Ú' => 'U',
	'Ů' => 'U',
	'ý' => 'y',
	'Ý' => 'Y',
	'ž' => 'z',
	'Ž' => 'Z'));

    //ukrainian alphabet in latin
//	 $string=str_replace('а','a',$string);
//	 $string=str_replace('б','b',$string);
//	 $string=str_replace('в','v',$string);
//	 $string=str_replace('г','g',$string);
//	 $string=str_replace('д','d',$string);
//	 $string=str_replace('е','e',$string);
//	 $string=str_replace('ё','yo',$string);
//	 $string=str_replace('ж','zh',$string);
//	 $string=str_replace('з','z',$string);
//	 $string=str_replace('и','i',$string);
//	 $string=str_replace('й','i',$string);
//	 $string=str_replace('к','k',$string);
//	 $string=str_replace('л','l',$string);
//	 $string=str_replace('м','m',$string);
//	 $string=str_replace('н','n',$string);
//	 $string=str_replace('о','o',$string);
//	 $string=str_replace('п','p',$string);
//	 $string=str_replace('р','r',$string);
//	 $string=str_replace('с','s',$string);
//	 $string=str_replace('т','t',$string);
//	 $string=str_replace('у','u',$string);
//	 $string=str_replace('ф','f',$string);
//	 $string=str_replace('х','h',$string);
//	 $string=str_replace('ц','ts',$string);
//	 $string=str_replace('ч','ch',$string);
//	 $string=str_replace('ш','sh',$string);
//	 $string=str_replace('щ','shch',$string);
//	 $string=str_replace('ь','',$string);
//	 $string=str_replace('ы','y',$string);
//	 $string=str_replace('ъ','',$string);
//	 $string=str_replace('э','e',$string);
//	 $string=str_replace('ю','yu',$string);
//	 $string=str_replace('я','ya',$string);
//	 $string=str_replace('і','i',$string);
//	 $string=str_replace('ї','yi',$string);
//	 $string=str_replace('є','ye',$string);
//
//	$string=str_replace('А','A',$string);
//	$string=str_replace('Б','B',$string);
//	$string=str_replace('В','V',$string);
//	$string=str_replace('Г','G',$string);
//	$string=str_replace('Д','D',$string);
//	$string=str_replace('Е','E',$string);
//	$string=str_replace('Ё','Yo',$string);
//	$string=str_replace('Ж','Zh',$string);
//	$string=str_replace('З','Z',$string);
//	$string=str_replace('И','I',$string);
//	$string=str_replace('Й','I',$string);
//	$string=str_replace('К','K',$string);
//	$string=str_replace('Л','L',$string);
//	$string=str_replace('М','M',$string);
//	$string=str_replace('Н','N',$string);
//	$string=str_replace('О','O',$string);
//	$string=str_replace('П','P',$string);
//	$string=str_replace('Р','R',$string);
//	$string=str_replace('С','S',$string);
//	$string=str_replace('Т','T',$string);
//	$string=str_replace('У','U',$string);
//	$string=str_replace('Ф','F',$string);
//	$string=str_replace('Х','H',$string);
//	$string=str_replace('Ц','Ts',$string);
//	$string=str_replace('Ч','Ch',$string);
//	$string=str_replace('Ш','Sh',$string);
//	$string=str_replace('Щ','Shch',$string);
//	$string=str_replace('Ь','',$string);
//	$string=str_replace('Ы','Y',$string);
//	$string=str_replace('Ъ','',$string);
//	$string=str_replace('Э','E',$string);
//	$string=str_replace('Ю','Yu',$string);
//	$string=str_replace('Я','Ya',$string);
//	$string=str_replace('І','I',$string);
//	$string=str_replace('Ї','Yi',$string);
//	$string=str_replace('Є','Ye',$string);
//
//	$string=str_replace(chr(197).chr(161),"s",$string);
//	$string=str_replace(chr(196).chr(141),"c",$string);
//	$string=str_replace(chr(197).chr(190),"z",$string);
//	$string=str_replace(chr(196).chr(145),"dz",$string);
//	$string=str_replace(chr(197).chr(160),"S",$string);
//	$string=str_replace(chr(196).chr(140),"C",$string);
//	$string=str_replace(chr(197).chr(189),"Z",$string);
//	$string=str_replace(chr(196).chr(144),"DZ",$string);
//
//
//	$string=str_replace(chr(196).chr(134),"C",$string);
//	$string=str_replace(chr(196).chr(135),"c",$string);
//
//	////Up there is utf8
//	$string=str_replace(chr(185),"s",$string);
//	$string=str_replace(chr(232),"c",$string);
//	$string=str_replace(chr(190),"z",$string);
//	$string=str_replace(chr(240),"dz",$string);
//	$string=str_replace(chr(169),"S",$string);
//	$string=str_replace(chr(200),"C",$string);
//	$string=str_replace(chr(174),"Z",$string);
//	$string=str_replace(chr(208),"DZ",$string);
//	////Up there is 8895-2 down there is 1250 ///////////////////
//	$string=str_replace(chr(154),"s",$string);
//	$string=str_replace(chr(232),"c",$string);
//	$string=str_replace(chr(158),"z",$string);
//	$string=str_replace(chr(240),"dz",$string);
//	$string=str_replace(chr(138),"S",$string);
//	$string=str_replace(chr(200),"C",$string);
//	$string=str_replace(chr(142),"Z",$string);
//	$string=str_replace(chr(208),"DZ",$string);
//	////Down there there is ukrainian to latin ///////////////////
//	$string=str_replace('б','b',$string);
//	$string=str_replace('в','v',$string);
//	$string=str_replace('г','g',$string);
//	$string=str_replace('д','d',$string);
//	$string=str_replace('е','e',$string);
//	$string=str_replace('ё','yo',$string);
//	$string=str_replace('ж','zh',$string);
//	$string=str_replace('з','z',$string);
//	$string=str_replace('и','i',$string);
//	$string=str_replace('й','i',$string);
//	$string=str_replace('к','k',$string);
//	$string=str_replace('л','l',$string);
//	$string=str_replace('м','m',$string);
//	$string=str_replace('н','n',$string);
//	$string=str_replace('о','o',$string);
//	$string=str_replace('п','p',$string);
//	$string=str_replace('р','r',$string);
//	$string=str_replace('с','s',$string);
//	$string=str_replace('т','t',$string);
//	$string=str_replace('у','u',$string);
//	$string=str_replace('ф','f',$string);
//	$string=str_replace('х','h',$string);
//	$string=str_replace('ц','ts',$string);
//	$string=str_replace('ч','ch',$string);
//	$string=str_replace('ш','sh',$string);
//	$string=str_replace('щ','shch',$string);
//	$string=str_replace('ь','',$string);
//	$string=str_replace('ы','y',$string);
//	$string=str_replace('ъ','',$string);
//	$string=str_replace('э','e',$string);
//	$string=str_replace('ю','yu',$string);
//	$string=str_replace('я','ya',$string);
//	$string=str_replace('і','i',$string);
//	$string=str_replace('ї','yi',$string);
//	$string=str_replace('є','ye',$string);


    return $string;
}
