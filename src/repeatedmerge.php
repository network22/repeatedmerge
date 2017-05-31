<?
/**
 * The utility to repeat merge between SVN branches during developing process.
 * https://github.com/network22/repeatedmerge
 */

// Get initial state
$g_InitialDir = getcwd();
$g_RunTime = gmdate("ymdHis");

// get parameters
if ( $argc < 2 )
{
	echo "Wrong parameter\n";
	echoUsage();
	exit( 1 );
}

if ( $argv[1] == "." )
{
	echo "The directory parameter can't be current dir\n";
	echoUsage();
	exit( 1 );
}

if ( @chdir( $argv[1] ) )
{
	$g_WorkDir = getcwd();
}
else
{
	echo "Wrong directory parameter\n";
	echoUsage();
	exit( 1 );
}

// get additional parameters
$g_Params = array();
for( $i = 2; $i < $argc; $i++ )
{
	if ( $argv[$i] == "--real" )
		$g_Params["real"] = true;
	elseif( $argv[$i] == "-i" )
		$g_Params["i"] = true;
}

if ( count($g_Params) != ($argc-2) )
{
	echo "Wrong number of parameters\n";
	echoUsage();
	exit( 1 );
}

if ( !getenv("COMSPEC") && isset($g_Params["i"]) )
{ // if no Windows OS - remove parameter "i"
	unset($g_Params["i"]);
}

// get SVN info about destination directory and check it
$result = array();
execsvn ( "svn info", $result, $retval );

if ( $retval != 0 )
{
	echo "Wrong directory: not a SVN directory\n";
	exit( 2 );
}

foreach( $result as $branchrev )
{
	list($prop,$val) = explode(':', $branchrev, 2);
	
	if ( strcmp($prop,"URL") == 0 )
		$workdirURL = $val;
	elseif ( strcmp($prop,"Repository Root") == 0 )
		$repRoot = $val;
}

if ( $repRoot && $workdirURL
		&& substr($workdirURL,0,strlen($repRoot))===$repRoot )
{
	$workdirPath = substr($workdirURL,strlen($repRoot));
}
else
{
	echo "Wrong directory: SVN dir mismatch to SVN root dir\n";
	exit( 2 );
}

$result = array();
execsvn ( "svn propget bswsvn:masterbranch", $result, $retval );

if ( $retval != 0 )
{
	echo "Wrong directory: can't get bswsvn:masterbranch property\n";
	exit( 2 );
}

if ( substr($result[0],0,1) != '/' )
{
	echo "Wrong directory: wrong bswsvn:masterbranch property\n";
	exit( 2 );
}

$masterBranch = $result[0];

// get last merge revision
$result = array();
execsvn ( "svn propget svn:mergeinfo", $result, $retval );

if ( $retval != 0 )
{
	echo "Wrong directory: can't get svn:mergeinfo property\n";
	exit( 2 );
}

foreach( $result as $branchrev )
{
	list($branch,$revperiod) = explode(':', $branchrev);
	
	if ( strcmp($branch,$masterBranch) == 0 )
	{
		list($_rev, $revStart) = explode('-', $branchrev);
	}
}


if ( !($revStart > 0) )
{
	echo "Can't get branch revision\n";
	exit( 3 );
}

// check if destination directory contains modifications
$result = array();
execsvn ( "svn st --ignore-externals -q", $result, $retval );

if ( $retval != 0 )
{
	echo "Can't check modification\n";
	exit( 4 );
}

$modifiedCount = 0;
foreach( $result as $line )
{
	$line = trim($line);
	if ( strlen($line) == 0 )
		continue;

	if ( substr($line, 0, 3) === 'X  ' )
		continue;

	$modifiedCount++;
}

if ( $modifiedCount > 0 )
{
	echo "Modification exists.\nUtility can't work if destination folder has modifications\n";
	exit( 11 );
}

// update destination directory before merge
$result = array();
execsvn ( "svn up -q", $result, $retval );

if ( $retval != 0 )
{
	echo "Can't update destination directory\n";
	exit( 5 );
}

$conflictCount = 0;
foreach( $result as $line )
{
	$line = trim($line);
	if ( strlen($line) == 0 )
		continue;

	if ( substr($line,0,1) === 'C' )
		$conflictCount++;
}

if ( $conflictCount > 0 )
{
	echo "Conflict found during update.\n";
	exit( 12 );
}




/*

Start merge

Use the following svn command
svn merge --dry-run --accept postpone {$repRoot}{$masterBranch} 
*/

$endRelease = "HEAD";

echo "Start merge ".(isset($g_Params["real"])?"in real":"simulation")."\n";
echo "$masterBranch:$revStart-$endRelease\n\n";

logActionDetail( "Start merge" );
logActionDetail( "$masterBranch:$revStart-$endRelease" );

$cmd = "svn merge -r $revStart:$endRelease ".(!isset($g_Params["real"])?"--dry-run":"")." --accept postpone {$repRoot}{$masterBranch}\n";
execsvn( $cmd, $result, $retval );

if ( $retval != 0 )
{
	echo "Can't run merge: internal error\n";
	exit( 13 );
}

// check conflicts
$conflictCount = 0;
foreach( $result as $line )
{
	$line = trim($line);
	if ( strlen($line) == 0 )
		continue;

	if ( preg_match('/^--- Merging r(\d+) through r(\d+) into/', $line, $match) )
	{
		if ( intval($match[1]) === intval($revStart+1) && intval($match[2]) > 0
					&& intval($match[2]) > intval($match[1]) )
		{
			$revEnd = intval($match[2]);
			$mergeNote = "Merge from {$masterBranch}: #{$revStart}-#{$revEnd}";
		}
	}

	if ( substr($line,0,1) === 'C' )
		$conflictCount++;
}

if ( $conflictCount > 0 )
{
	echo "Conflict exists.\n";
	echo "Please resolve them manually.\n";
	echo "Note to commit:\n";
	echo "$mergeNote\n";
	exit( 14 );
}

if ( isset($g_Params["i"]) )
{
	$cmd = "TortoiseProc.exe /command:commit /path:\"{$g_WorkDir}\" /logmsg:\"{$mergeNote}\" /closeonend:0";
}
else
{
	$cmd = "svn commit -m \"{$mergeNote}\"";
}

if ( isset($g_Params["real"]) )
{
	execsvn( $cmd, $result, $retval );
}

if ( isset($g_Params["real"]) )
	echo "Merge finished.\n";
else
	echo "Merge simulation finished.\n";

echo "$mergeNote\n";


//////////////////////////////////////////////////////////////////

function execsvn( $cmd, &$result, &$retval )
{
	logActionDetail( "Run:{$cmd}" );
	exec ( $cmd, $result, $retval );

	if ( $retval == 0 )
		logActionDetail( "Success returned: {$retval}" );
	else
		logActionDetail( "Error returned: {$retval}", true );

	logActionDetail( "Return detail: ".print_r($result,1) );

	return $retval;
}

function logActionDetail( $msg, $isError=false )
{
  global $g_RunTime, $g_InitialDir;

  $str = gmdate("Y-m-d H:i:s").($isError?" ERROR: ":": ").$msg."\n";

  if ($fh=fopen($g_InitialDir."/svn_rmerge_{$g_RunTime}.log","a"))
  {
    fwrite($fh, $str);
    fclose($fh);
  }
}

function echoUsage()
{
	echo "\nUsage:\n  repeatedmerge.php target_dir [--real] [-i]\n";
	echo "    --real - do merge in real, without it - just simulate\n";
	echo "    -i - use TortoiseSVN for commit (Windows only)\n";
}

?>