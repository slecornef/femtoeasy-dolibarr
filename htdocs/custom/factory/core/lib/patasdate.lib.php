<?php
/* Copyright (C) 2014-2018 Charlene BENKE  <charlie@patas-monkey.com>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
 * or see http://www.gnu.org/
 */

/**
 *		\file	   /factory/lib/factory.lib.php
 *		\brief	  Ensemble de fonctions de base pour le module factory
 *	  \ingroup	factory
 */

/**
 * générate standard filter date
 *
 * @param      string	$datefield		fields where apply sql date filter
 * @param      int		$day_date		day date
 * @param      int		$month_date		month date
 * @param      int		$year_date		year date
 
 * @return     string	$sqldate		sql part of date
 */
 function dol_sql_datefilter($datefield, $day_date, $month_date, $year_date) {
	global $db;
	$sqldate="";
	if ($month_date > 0) {
		if ($year_date > 0 && empty($day_date)) {
			$sqldate.= " AND ".$datefield." BETWEEN '".$db->idate(dol_get_first_day($year_date, $month_date, false));
			$sqldate.= "' AND '".$db->idate(dol_get_last_day($year_date, $month_date, false))."'";
		} else if ($year_date > 0 && ! empty($day_date)) {
			$sqldate.= " AND ".$datefield." BETWEEN '".$db->idate(dol_mktime(0, 0, 0, $month_date, $day_date, $year_date));
			$sqldate.= "' AND '".$db->idate(dol_mktime(23, 59, 59, $month_date, $day_date, $year_date))."'";
		} else
			$sqldate.= " AND date_format( ".$datefield.", '%m') = '".$db->escape($month_date)."'";
	} else if ($year_date > 0){
		$sqldate.= " AND ".$datefield." BETWEEN '".$db->idate(dol_get_first_day($year_date, 1, false));
		$sqldate.= "' AND '".$db->idate(dol_get_last_day($year_date, 12, false))."'";
	}
	return $sqldate;
}