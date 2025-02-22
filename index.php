<?php
session_start();

function redirect($url)
// https://stackoverflow.com/a/7066882
{
    $string = '<script type="text/javascript">';
    $string .= 'window.location = "' . $url . '"';
    $string .= '</script>';

    echo $string;
}

function generate_list($model)
{
    $list_item = '<li><a href="'.$_SERVER['PHP_SELF']."?scadfile=".$model.'">';
    $list_item .= $model;
    $list_item .= '</a></li>';

    return $list_item;
}

// Parameters
$workdir = $_SERVER['DOCUMENT_ROOT'].'/most-3d-customizer/files/';
$originals_dir = $_SERVER['DOCUMENT_ROOT'].'/most-3d-customizer/start/';

?>
<!DOCTYPE html>
<!-- http://localhost/most-3d-customizer/index.php?scadfile=example.scad -->

<html>
<head>
<meta charset="utf-8" />
<title>Open Source 3D SCAD Customizer</title>
<?php
require $_SERVER['DOCUMENT_ROOT'].'/most-3d-customizer/vendor/autoload.php';
//@ require dirname(__FILE__).'/vendor/autoload.php';
?>
</head>
<link href="https://fonts.googleapis.com/css?family=Bungee+Shade" rel="stylesheet">
<style>
h1 {
font-family: 'Bungee Shade', cursive;
}
</style>

<body>
<script type="text/javascript">
function showValue(newValue,elemId)
{
	document.getElementById(elemId).innerHTML=newValue;
}
</script>
<table cellpadding="10">
<tr>
<td>
<table cellpadding="10">
<tr>
<td align="right"><img src="./image/MOST_logo.png" height="107"></td>
<td align="left" valign="top"><h1>Free Open Source<br>3-D Customizer</h1></td>
</tr>
</table>
<?php
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
	if ($_GET['clear_temp'] == "true"){
		if (is_dir($workdir)) {
			$temp_files = scandir($workdir);
			$size = 0;
			$count = 0;
			foreach ($temp_files as $temp_file){
				
				if ($temp_file != '.' && $temp_file != '..' && $temp_file != '.gitkeep') {
					$count += 1;
					$size += stat($workdir.'/'.$temp_file)['size'];
					unlink($workdir.'/'.$temp_file);
				}
			}

			?>
				<hr>
				<h3>⚠️ Cleared <?php echo $count ?> Temporary Files - <?php echo "Total: ".$size." Bytes" ?> ⚠️</h3>
				<hr>
			<?php
		}
		else {
			echo "<h1>Configuration Error, Provided Path is not a Valid Directory!</h1>";
		}
	}
	if (!$_GET['scadfile']){
		if (is_dir($originals_dir)) {
			?>
				<h2>Available SCAD Models</h2>
				<ul>
				<?php

				$models = scandir($originals_dir);
				foreach ($models as $model){
					$pathInfo = pathinfo($model);
					if ($pathInfo['extension'] == 'scad'){
						echo generate_list($model);
					}
				}

				?>
				</ul>
			<?php
		}
		else {
			echo "<h1>Configuration Error, Provided Path is not a Valid Directory!</h1>";
		}
	}
	else {
		?>
		<h2 align="left" valign="top">File: <?php echo $_GET['scadfile'] ?></h2>
		<a href="<?php echo $_SERVER['PHP_SELF'] ?>"><- Back to file list</a>
		<?php
		//@ echo "Beginning of file...<br>";

		if (file_exists($workdir.$_GET['scadfile'])){
			// File has already been copied / edited in working directory
			$scadfile = $workdir.$_GET['scadfile'];
		}
		else {
			// First time edit, use file from originals folder
			$scadfile = $_SERVER['DOCUMENT_ROOT'].'/most-3d-customizer/start/'.$_GET['scadfile'];
		}

		//@ echo "Got $scadfile ...<br>";
		
		$render3d = new \Libre3d\Render3d\Customizer();
		
		
		//@ echo "Successful initiated class Render3d...<br>";
		
		// this is the working directory, where it will put any files used during the render process, as well as the final
		// rendered image.
		//@ workingDir need to have 777 permission.
		$render3d->workingDir($workdir); 
		//@ $render3d->workingDir(dirname(__FILE__).'/files/');
		
		//@ echo "Successful assigned working directory...<br>";
		
		// Set paths to the executables on this system
		$render3d->executable('openscad', '/usr/bin/openscad');
		
		//@ echo "Successful executable openscad...<br>";
		
		$render3d->executable('povray', '/usr/bin/povray');
		
		//@ echo "Successful executable povray...<br>";
		
		try {
			// This will copy in your starting file into the working DIR if you give the full path to the starting file.
			// This will also set the fileType for you.
			$render3d->filename($scadfile);
			//@ $render3d->filename(dirname(__FILE__).'/start/example.stl');
			
			// Render!  This will do all the necessary conversions as long as the render engine (in this
			// case, the default engine, PovRAY) "knows" how to convert the file into a file it can use for rendering.
			// Note that this is a multi-step process that can be further broken down if you need it to.
			$renderedImagePath = $render3d->render('povray');
		
			//@ echo "Render successful!  Rendered image will be at $renderedImagePath <br>";
		?>
		<table cellpadding="10">
		<tr>
			<td valign="top">
				<p>
					<div style="text-align: center"><img src="<?= str_replace($_SERVER['DOCUMENT_ROOT'],'',$renderedImagePath) ?>" width="500" height="375"></div>
				</p>
				<a href="<?php echo str_replace($_SERVER['DOCUMENT_ROOT'],'',$workdir.''.pathinfo($scadfile,PATHINFO_FILENAME).'.stl') ?>">Download STL</a>
			</td>
		<?php
		} catch (\Exception $e) {
			echo "Render failed :( Exception: ".$e->getMessage()."<br>";
		} 
		//@ folder start need to have 777 permission
		$result = $render3d->readSCAD($scadfile);
		if(empty($result)) {
			echo "Something is wrong with the file!";
		}
		else {
			//@ echo "The SCAD file is customizable!<br>";
			//$_SESSION['parameters'] = array();
			$_SESSION['parameters'] = $result;
			//@ serialize class object before it can be stored in session
			$_SESSION['render3d'] = serialize($render3d);
			//$storeClass = = serialize($render3d);
			//file_put_contents('storeClass',$storeClass);
			$_SESSION['scadfile'] = $scadfile;
			//@ count how many rows
			$rows = count($result);
			?>
			<td valign="top">
			<form name="submitForm" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]);?>" method="post" >
			<?php
			//@ 0:description, 1:variable name, 2: value, 3:possible value
			for($i=0;$i<$rows;$i++) {
				//@ echo $result[$i][0]." " .$result[$i][1]." ". $result[$i][2]." ". $result[$i][3]."<br>";
				$element = $render3d->generateElement($result[$i],$i);
				echo $element;
			}

			?>
				<input type="submit" id="submit" value="Save">
			</form>
			</td></tr></table>
			</td>
			</tr>
			</table>
			<?php
		}
		
		//@ echo "End of file...<br>";
	}
}
else {
	//@ Save button was clicked
	//@ echo "Save was clicked!<br>";
	/* if(empty($_SESSION['parameters'])) {
		echo "Session is empty!<br>";
	}
	else {
		echo "Session is not empty!<br>";
	}*/
	//$result = array();
	$result = $_SESSION['parameters'];
	//@ count how many rows
	$rows = count($result);
	//@ echo "There are ". $rows . " rows!<br>";
	//@ update value of each parameter
	//@ 0:description, 1:variable name, 2: value, 3:possible value
	for($i=0;$i<$rows;$i++) {
		$result[$i][2] = htmlspecialchars($_POST['var'.$i]);
		//@ echo $result[$i][0] ." " . $result[$i][1] ." ". $result[$i][2] ." ". $result[$i][3] ."<br>";
	}

	//@ update parameters to a new scad file
	//@ unserialize class object to variable
	$render3d = unserialize($_SESSION['render3d']);
	//$storeClass = file_get_contents('storeClass');
	//$render3d = unserialize($storeClass);
	$scadfile = $_SESSION['scadfile'];
	//@ echo "original scad file ".$scadfile." ...<br>";
	$newscadname = $render3d->writeSCAD($scadfile, $result);
	//@ echo "Successfully created new SCAD file ".$newscadname." !<br>";
	//@ reload itself with new scasd filename
	redirect($_SERVER['PHP_SELF'].'?scadfile='.$newscadname);
	//header('Location:'.$_SERVER['PHP_SELF'].'?scadfile='.$newscadname);
	exit;
}
?>
<br/>
<a href="<?php echo $_SERVER['PHP_SELF'].'?clear_temp=true' ?>">Clear Temporary Files</a>
<br/>
<br/>
<p>
	v1.0.0alpha by Francesco SERVIDA, University of Lausanne <br/>
	Based on work by Michigan Tech's Open Sustainability Technology Lab (https://github.com/mtu-most/most-3-d-customizer) and Jonathan Foote (https://github.com/libre3d/render-3d)</p>
</body>
</html>