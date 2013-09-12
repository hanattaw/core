<?
/*
Gibbon, Flexible & Open School System
Copyright (C) 2010, Ross Parker

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program. If not, see <http://www.gnu.org/licenses/>.
*/

include "../../functions.php" ;
include "../../config.php" ;

//New PDO DB connection
try {
  	$connection2=new PDO("mysql:host=$databaseServer;dbname=$databaseName;charset=utf8", $databaseUsername, $databasePassword);
	$connection2->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
	$connection2->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
}
catch(PDOException $e) {
  echo $e->getMessage();
}

session_start() ;

//Set timezone from session variable
date_default_timezone_set($_SESSION[$guid]["timezone"]);

$gibbonSchoolYearID=$_GET["gibbonSchoolYearID"];
$gibbonCourseID=$_GET["gibbonCourseID"]; 
$gibbonCourseClassID=$_GET["gibbonCourseClassID"]; 
$gibbonUnitID=$_GET["gibbonUnitID"]; 
$gibbonUnitClassID=$_GET["gibbonUnitClassID"]; 
$orders=$_POST["order"] ;

//IF UNIT DOES NOT CONTAIN HYPHEN, IT IS A GIBBON UNIT
$gibbonUnitID=$_GET["gibbonUnitID"]; 
if (strpos($gibbonUnitID,"-")==FALSE) {
	$hooked=FALSE ;
}
else {
	$hooked=TRUE ;
	$gibbonHookIDToken=substr($gibbonUnitID,11) ;
	$gibbonUnitIDToken=substr($gibbonUnitID,0,10) ;
}

$URL=$_SESSION[$guid]["absoluteURL"] . "/index.php?q=/modules/" . getModuleName($_GET["address"]) . "/units_edit_working.php&gibbonSchoolYearID=$gibbonSchoolYearID&gibbonCourseID=$gibbonCourseID&gibbonUnitID=$gibbonUnitID&gibbonCourseClassID=$gibbonCourseClassID&gibbonUnitClassID=$gibbonUnitClassID" ;

if (isActionAccessible($guid, $connection2, "/modules/Planner/units_edit_working.php")==FALSE) {
	//Fail 0
	$URL = $URL . "&updateReturn=fail0" ;
	header("Location: {$URL}");
}
else {
	$highestAction=getHighestGroupedAction($guid, $_GET["address"], $connection2) ;
	if ($highestAction==FALSE) {
		//Fail 0
		$URL = $URL . "&updateReturn=fail0$params" ;
		header("Location: {$URL}");
	}
	else {
		//Proceed!
		//Validate Inputs
		if ($gibbonSchoolYearID=="" OR $gibbonCourseID=="" OR $gibbonUnitID=="" OR $orders=="") {
			//Fail 3
			$URL = $URL . "&updateReturn=fail3" ;
			header("Location: {$URL}");
		}
		else {
			//Check access to specified course
			try {
				if ($highestAction=="Manage Units_all") {
					$data=array("gibbonSchoolYearID"=>$gibbonSchoolYearID, "gibbonCourseID"=>$gibbonCourseID); 
					$sql="SELECT * FROM gibbonCourse WHERE gibbonSchoolYearID=:gibbonSchoolYearID AND gibbonCourseID=:gibbonCourseID" ;
				}
				else if ($highestAction=="Manage Units_learningAreas") {
					$data=array("gibbonSchoolYearID"=>$gibbonSchoolYearID, "gibbonCourseID"=>$gibbonCourseID, "gibbonPersonID"=>$_SESSION[$guid]["gibbonPersonID"]); 
					$sql="SELECT gibbonCourseID, gibbonCourse.name, gibbonCourse.nameShort FROM gibbonCourse JOIN gibbonDepartment ON (gibbonCourse.gibbonDepartmentID=gibbonDepartment.gibbonDepartmentID) JOIN gibbonDepartmentStaff ON (gibbonDepartmentStaff.gibbonDepartmentID=gibbonDepartment.gibbonDepartmentID) WHERE gibbonDepartmentStaff.gibbonPersonID=:gibbonPersonID AND (role='Coordinator' OR role='Assistant Coordinator' OR role='Teacher (Curriculum)') AND gibbonSchoolYearID=:gibbonSchoolYearID AND gibbonCourseID=:gibbonCourseID ORDER BY gibbonCourse.nameShort" ;
				}
				$result=$connection2->prepare($sql);
				$result->execute($data);
			}
			catch(PDOException $e) { 
				//Fail 2
				$URL = $URL . "&addReturn=fail2" ;
				header("Location: {$URL}");
				break ;
			}
			
			if ($result->rowCount()!=1) {
				//Fail 4
				$URL = $URL . "&updateReturn=fail4" ;
				header("Location: {$URL}");
			}
			else {
				//Check existence of specified unit
				if ($hooked==FALSE) {
					try {
						$data=array("gibbonUnitID"=>$gibbonUnitID, "gibbonCourseID"=>$gibbonCourseID); 
						$sql="SELECT gibbonCourse.nameShort AS courseName, gibbonUnit.* FROM gibbonUnit JOIN gibbonCourse ON (gibbonUnit.gibbonCourseID=gibbonCourse.gibbonCourseID) WHERE gibbonUnitID=:gibbonUnitID AND gibbonUnit.gibbonCourseID=:gibbonCourseID" ;
						$result=$connection2->prepare($sql);
						$result->execute($data);
					}
					catch(PDOException $e) { 
						//Fail 2
						$URL = $URL . "&deployReturn=fail2" ;
						header("Location: {$URL}");
						break ;
					}
				}
				else {
					try {
						$dataHooks=array("gibbonHookID"=>$gibbonHookIDToken); 
						$sqlHooks="SELECT * FROM gibbonHook WHERE type='Unit' AND gibbonHookID=:gibbonHookID ORDER BY name" ;
						$resultHooks=$connection2->prepare($sqlHooks);
						$resultHooks->execute($dataHooks);
					}
					catch(PDOException $e) { 
						//Fail 2
						$URL = $URL . "&deployReturn=fail2" ;
						header("Location: {$URL}");
						break ;
					}
					if ($resultHooks->rowCount()==1) {
						$rowHooks=$resultHooks->fetch() ;
						$hookOptions=unserialize($rowHooks["options"]) ;
						if ($hookOptions["unitTable"]!="" AND $hookOptions["unitIDField"]!="" AND $hookOptions["unitCourseIDField"]!="" AND $hookOptions["unitNameField"]!="" AND $hookOptions["unitDescriptionField"]!="" AND $hookOptions["classLinkTable"]!="" AND $hookOptions["classLinkJoinFieldUnit"]!="" AND $hookOptions["classLinkJoinFieldClass"]!="" AND $hookOptions["classLinkIDField"]!="") {
							try {
								$data=array("unitIDField"=>$gibbonUnitIDToken); 
								$sql="SELECT " . $hookOptions["unitTable"] . ".*, gibbonCourse.nameShort FROM " . $hookOptions["unitTable"] . " JOIN gibbonCourse ON (" . $hookOptions["unitTable"] . "." . $hookOptions["unitCourseIDField"] . "=gibbonCourse.gibbonCourseID) WHERE " . $hookOptions["unitIDField"] . "=:unitIDField" ;
								$result=$connection2->prepare($sql);
								$result->execute($data);
							}
							catch(PDOException $e) { 
								//Fail 2
								$URL = $URL . "&deployReturn=fail2" ;
								header("Location: {$URL}");
								break ;
							}									
						}
					}
				}
				
				if ($result->rowCount()!=1) {
					//Fail 4
					$URL = $URL . "&updateReturn=fail4" ;
					header("Location: {$URL}");
				}
				else {
					$row=$result->fetch() ;
					
					//Remove all blocks
					try {
						if ($hooked==FALSE) {
							$data=array("gibbonUnitClassID"=>$gibbonUnitClassID); 
							$sql="DELETE FROM gibbonUnitClassBlock WHERE gibbonUnitClassID=:gibbonUnitClassID" ;
						}
						else {
							$data=array("gibbonUnitClassID"=>$gibbonUnitClassID); 
							$sql="DELETE FROM " . $hookOptions["classSmartBlockTable"] . " WHERE " . $hookOptions["classSmartBlockJoinField"] . "=:gibbonUnitClassID" ;
						}
						$result=$connection2->prepare($sql);
						$result->execute($data);
					}
					catch(PDOException $e) { 
						//Fail 2
						$URL = $URL . "&updateReturn=fail2" ;
						header("Location: {$URL}");
						break ;
					}
					
					$partialFail=false;
					
					$lessonCount=0 ;
					$sequenceNumber=0 ;
					foreach ($orders AS $order) {
						//It is a lesson, get gibbonPlannerID
						if (strpos($order, "lessonHeader-")!==FALSE) {
							$AI=$_POST["gibbonPlannerEntryID$lessonCount"] ;
							$lessonCount++ ;
						}
						//It is a block, so add it to the last added lesson
						else {
							$titles=$_POST["title" . $order] ;
							$types=$_POST["type" . $order] ;
							$lengths=$_POST["length" . $order] ;
							$completes=$_POST["complete" . $order] ;
							if ($completes=="on") {
								$completes="Y" ;
							}
							else {
								$completes="N" ;
							}
							$contents=$_POST["contents" . $order] ;
							$teachersNotes=$_POST["teachersNotes" . $order] ;
							$gibbonUnitBlockID=$_POST["gibbonUnitBlockID" . $order] ;
							
							try {
								if ($hooked==FALSE) {
									$data=array("gibbonUnitClassID"=>$gibbonUnitClassID, "gibbonPlannerEntryID"=>$AI, "gibbonUnitBlockID"=>$gibbonUnitBlockID, "title"=>$titles, "type"=>$types, "length"=>$lengths, "complete"=>$completes, "contents"=>$contents, "teachersNotes"=>$teachersNotes, "sequenceNumber"=>$sequenceNumber); 
									$sql="INSERT INTO gibbonUnitClassBlock SET gibbonUnitClassID=:gibbonUnitClassID, gibbonPlannerEntryID=:gibbonPlannerEntryID, gibbonUnitBlockID=:gibbonUnitBlockID, title=:title, type=:type, length=:length, complete=:complete, contents=:contents, teachersNotes=:teachersNotes, sequenceNumber=:sequenceNumber" ;
								}
								else {
									$data=array("gibbonUnitClassID"=>$gibbonUnitClassID, "gibbonPlannerEntryID"=>$AI, "gibbonUnitBlockID"=>$gibbonUnitBlockID, "title"=>$titles, "type"=>$types, "length"=>$lengths, "complete"=>$completes, "contents"=>$contents, "teachersNotes"=>$teachersNotes, "sequenceNumber"=>$sequenceNumber); 
									$sql="INSERT INTO " . $hookOptions["classSmartBlockTable"] . " SET " . $hookOptions["classSmartBlockJoinField"] . "=:gibbonUnitClassID, " . $hookOptions["classSmartBlockPlannerJoin"] . "=:gibbonPlannerEntryID, " . $hookOptions["classSmartBlockUnitBlockJoinField"] . "=:gibbonUnitBlockID, " . $hookOptions["classSmartBlockTitleField"] . "=:title, " . $hookOptions["classSmartBlockTypeField"] . "=:type, " . $hookOptions["classSmartBlockLengthField"] . "=:length, " . $hookOptions["classSmartBlockCompleteField"] . "=:complete, " . $hookOptions["classSmartBlockContentsField"] . "=:contents, " . $hookOptions["classSmartBlockTeachersNotesField"] . "=:teachersNotes, " . $hookOptions["classSmartBlockSequenceNumberField"] . "=:sequenceNumber" ;
								}
								$result=$connection2->prepare($sql);
								$result->execute($data);
							}
							catch(PDOException $e) {
								print $e->getMessage() ; 
								$partialFail=true;
							}
							$sequenceNumber++ ;
						}
					}

					
					//RETURN
					if ($partialFail==TRUE) {
						//Fail 6
						$URL = $URL . "&updateReturn=fail6" ;
						header("Location: {$URL}");
					}
					else {
						$URL = $URL . "&updateReturn=success0" ;
						header("Location: {$URL}") ;
					}
				}
			}
		}
	}
}
?>