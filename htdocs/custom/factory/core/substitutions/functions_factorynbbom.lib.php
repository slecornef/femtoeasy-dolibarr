<?php
/* Copyright (C) 2014-2022	Charlene BENKE	<charlene@patas-monkey.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program. If not, see <http://www.gnu.org/licenses/>.
 * or see http://www.gnu.org/
 */

/*		Function called to complete substitution array (before generating on ODT, or a personalized email)
 *		functions xxx_completesubstitutionarray are called by make_substitutions() if file
 *		is inside directory htdocs/core/substitutions
 *
 *		@param	array		$substitutionarray	Array with substitution key=>val
 *		@param	Translate	$langs				Output langs
 *		@param	Object		$object				Object to use to get values
 *		@return	void		The entry parameter $substitutionarray is modified
 */
function factorynbbom_completesubstitutionarray(&$substitutionarray, $langs, $object, $parameters)
{
	global $db;
	$infoAdd="";
	$nbBuild =0;

	if ($object == null) 
	return 0;

	// si il y a des fabrication associé à cette propale
	$sql = "SELECT * FROM ".MAIN_DB_PREFIX."factory ";
	$sql .= " WHERE fk_bom= ".$object->id;
	$result = $db->query($sql);
	if ($result) {
		if ($db->num_rows($result)) {
			$obj = $db->fetch_object($result);
			$nbBuild = $obj->nb;
		}
	}

	//on ajoute le badge des options
	if ($nbBuild)
		$infoAdd= '<span  style="background-color:green;" class="badge">'.$nbBuild.'</span>';

	$substitutionarray['factorynbbom']="Factory".$infoAdd;
}
