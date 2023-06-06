<?php
/* Copyright (C) 2001-2007	Rodolphe Quiedeville  <rodolphe@quiedeville.org>
 * Copyright (C) 2004-2016	Laurent Destailleur <eldy@users.sourceforge.net>
 * Copyright (C) 2004    	Eric Seigne         <eric.seigne@ryxeo.com>
 * Copyright (C) 2005    	Marc Barilley / Ocebo	<marc@ocebo.com>
 * Copyright (C) 2005-2013	Regis Houssin       <regis.houssin@capnetworks.com>
 * Copyright (C) 2006    	Andre Cianfarani    <acianfa@free.fr>
 * Copyright (C) 2010-2011	Juanjo Menent       <jmenent@2byte.es>
 * Copyright (C) 2010-2011	Philippe Grand      <philippe.grand@atoo-net.com>
 * Copyright (C) 2012   	Christophe Battarel <christophe.battarel@altairis.fr>
 * Copyright (C) 2013   	Cédric Salvador    	<csalvador@gpcsolutions.fr>
 * Copyright (C) 2015   	Jean-François Ferry	<jfefe@aternatik.fr>
 * Copyright (C) 2016-2018	Ferran Marcet		<fmarcet@2byte.es>
 * Copyright (C) 2018		Nicolas ZABOURI		<info@inovea-conseil.com>
 * Copyright (C) 2017-2022	Charlene Benke		<charlie@patas-monkey.com>
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
 */

/**
 *		\file	   htdocs/factory/list.php
 *	  \ingroup	factory
 *		\brief	  Page to list all factory process
 */

$res=@include("../main.inc.php");					// For root directory
if (! $res && file_exists($_SERVER['DOCUMENT_ROOT']."/main.inc.php"))
	$res=@include($_SERVER['DOCUMENT_ROOT']."/main.inc.php"); // Use on dev env only
if (! $res) $res=@include("../../main.inc.php");		// For "custom" directory

require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formfile.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formcompany.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/date.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/company.lib.php';

if (!function_exists('dol_sql_datefilter')) {
	dol_include_once('/factory/core/lib/patasdate.lib.php');
}
require_once DOL_DOCUMENT_ROOT."/core/lib/product.lib.php";
require_once DOL_DOCUMENT_ROOT."/product/class/product.class.php";
require_once DOL_DOCUMENT_ROOT."/product/stock/class/entrepot.class.php";
require_once DOL_DOCUMENT_ROOT."/categories/class/categorie.class.php";
require_once DOL_DOCUMENT_ROOT."/core/lib/date.lib.php";

dol_include_once('/factory/class/factory.class.php');
dol_include_once('/factory/core/lib/factory.lib.php');



// Load translation files required by the page
$langs->load('companies');
$langs->load('products');
$langs->load('factory@factory');


$factoryid=GETPOST('factoryid', 'int');
$id=GETPOST('id', 'int');
$ref=GETPOST('ref', 'alpha');
$action=GETPOST('action', 'alpha');
$confirm=GETPOST('confirm', 'alpha');
$cancel=GETPOST('cancel', 'alpha');
$key=GETPOST('key');
$parent=GETPOST('parent');

$massaction=GETPOST('massaction', 'alpha');
$show_files=GETPOST('show_files', 'int');

$toselect = GETPOST('toselect', 'array');
$contextpage=GETPOST('contextpage', 'aZ')?GETPOST('contextpage', 'aZ'):'factorylist';

$search_user=GETPOST('search_user', 'int');
$search_ref=GETPOST('sf_ref')?GETPOST('sf_ref', 'alpha'):GETPOST('search_ref', 'alpha');

$search_refproduct=GETPOST('search_refproduct', 'alpha');
$search_product_category=GETPOST('search_product_category', 'int');
$search_refentrepot=GETPOST("search_refentrepot", 'alpha');

$search_day_create=GETPOST("search_day_create", "int");
$search_month_create=GETPOST("search_month_create", "int");
$search_year_create=GETPOST("search_year_create", "int");

$search_day_start_planned=GETPOST("search_day_start_planned", "int");
$search_month_start_planned=GETPOST("search_month_start_planned", "int");
$search_year_start_planned=GETPOST("search_year_start_planned", "int");

$search_day_end_planned=GETPOST("search_day_end_planned", "int");
$search_month_end_planned=GETPOST("search_month_end_planned", "int");
$search_year_end_planned=GETPOST("search_year_end_planned", "int");

$search_qty_planned=GETPOST("search_qty_planned", "int");
$search_duration_planned=GETPOST("search_duration_planned", "int");

$search_day_start_made=GETPOST("search_day_start_made", "int");
$search_month_start_made=GETPOST("search_month_start_made", "int");
$search_year_start_made=GETPOST("search_year_start_made", "int");

$search_day_end_made=GETPOST("search_day_end_made", "int");
$search_month_end_made=GETPOST("search_month_end_made", "int");
$search_year_end_made=GETPOST("search_year_end_made", "int");

$search_qty_made=GETPOST("search_qty_made", "int");
$search_duration_made=GETPOST("search_duration_made", "int");

$search_btn=GETPOST('button_search', 'alpha');
$search_remove_btn=GETPOST('button_removefilter', 'alpha');

$viewstatut=GETPOST('viewstatut', 'alpha');
$optioncss = GETPOST('optioncss', 'alpha');
$object_statut=GETPOST('search_statut', 'alpha');

$sall=trim((GETPOST('search_all', 'alphanohtml')!='')?GETPOST('search_all', 'alphanohtml'):GETPOST('sall', 'alphanohtml'));
$mesg=(GETPOST("msg") ? GETPOST("msg") : GETPOST("mesg"));


$limit = GETPOST('limit', 'int')?GETPOST('limit', 'int'):$conf->liste_limit;
$sortfield = GETPOST("sortfield", 'alpha');
$sortorder = GETPOST("sortorder", 'alpha');
$page = GETPOST("page", 'int');

// If $page is not defined, or '' or -1
if (empty($page) || $page == -1 || !empty($search_btn) || !empty($search_remove_btn) || (empty($toselect) && $massaction === '0'))
	$page = 0;

$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;
if (! $sortfield) $sortfield='f.ref';
if (! $sortorder) $sortorder='DESC';

// Security check
$module='factory';
$dbtable='';
$objectid='';
$socid="";
if (! empty($user->socid))	
	$socid=$user->socid;
$result = restrictedArea($user, $module, $objectid, $dbtable);

$diroutputmassaction=$conf->factory->multidir_output[$conf->entity] . '/temp/massgeneration/'.$user->id;

// Initialize technical object to manage hooks of page. Note that conf->hooks_modules contains array of hook context
$product = new Product($db);
$object = new Factory($db);
$entrepot=new Entrepot($db);

$hookmanager->initHooks(array('factorylist'));
$extrafields = new ExtraFields($db);

// fetch optionals attributes and labels
$extralabels = $extrafields->fetch_name_optionals_label('factory');
$search_array_options=$extrafields->getOptionalsFromPost($extralabels,'','search_');

// List of fields to search into when doing a "search in all"
$fieldstosearchall = array(
	'f.ref'=>'Ref',
	'p.ref'=>'RefProduct',
	'e.ref'=>'RefEntrepot',
	'f.description'=>'Description',
	'f.note_public'=>'NotePublic',
);
if (empty($user->socid)) $fieldstosearchall["f.note_private"]="NotePrivate";


$checkedtypetiers=0;
$arrayfields=array(
	'f.ref'=>array('label'=>$langs->trans("Ref"), 'checked'=>1),
	'p.ref'=>array('label'=>$langs->trans("RefProducts"), 'checked'=>1),
	'e.ref'=>array('label'=>$langs->trans("RefWareHouse"), 'checked'=>1),
	'f.datec'=>array('label'=>$langs->trans("DateCreation"), 'checked'=>0, 'position'=>500),
	'f.qty_planned'=>array('label'=>$langs->trans("FactoryQtyPlanned"), 'checked'=>1, 'position'=>500),
	'f.duration_planned'=>array('label'=>$langs->trans("FactoryDurationPlanned"), 'checked'=>0, 'position'=>500),
	'f.date_start_planned'=>array('label'=>$langs->trans("FactoryDateStartPlanned"), 'checked'=>1),
	'f.date_end_planned'=>array('label'=>$langs->trans("FactoryDateEndPlanned"), 'checked'=>1),
	'f.qty_made'=>array('label'=>$langs->trans("FactoryQtyMade"), 'checked'=>1, 'position'=>500),
	'f.duration_made'=>array('label'=>$langs->trans("DurationMade"), 'checked'=>0, 'position'=>500),
	'f.date_start_made'=>array('label'=>$langs->trans("DateStartMade"), 'checked'=>1),
	'f.date_end_made'=>array('label'=>$langs->trans("DateEndMade"), 'checked'=>1),
	'f.controling'=>array('label'=>$langs->trans("Controling"), 'checked'=>0),
	'f.fk_statut'=>array('label'=>$langs->trans("Status"), 'checked'=>1, 'position'=>1000),
);
// Extra fields
$efLabel = $extrafields->attributes[$object->table_element];
if (!empty($efLabel['label']) && is_array($efLabel['label']) && count($efLabel['label'])) {
	foreach($efLabel['label'] as $key => $val) {
		$arrayfields["ef.".$key]=array(
				'label'=>$efLabel['label'][$key], 
				'checked'=>(($efLabel['list'][$key]<0)?0:1), 
				'position'=>$efLabel['pos'][$key], 
				'enabled'=>(abs($efLabel['list'][$key])!=3 && $efLabel['perms'][$key]));
	}
}


/*
 * Actions
 */

if (GETPOST('cancel','alpha')) { $action='list'; $massaction=''; }
if (! GETPOST('confirmmassaction','alpha') && $massaction != 'presend' && $massaction != 'confirm_presend') { $massaction=''; }

$parameters=array('socid'=>$socid);
$reshook=$hookmanager->executeHooks('doActions',$parameters,$object,$action);    // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');

include DOL_DOCUMENT_ROOT.'/core/actions_changeselectedfields.inc.php';

// Do we click on purge search criteria ?
if (GETPOST('button_removefilter_x','alpha') || GETPOST('button_removefilter.x','alpha') || GETPOST('button_removefilter','alpha')) // All tests are required to be compatible with all browsers
{
	$search_categ='';
	$search_user='';
	$search_sale='';
	$search_ref='';
	$search_refproduct='';
	$search_refentrepot='';

	$search_year_create='';
	$search_month_create='';
	$search_day_create='';
	
	$search_year_start_planned='';
	$search_month_start_planned='';
	$search_day_start_planned='';

	$search_year_end_planned='';
	$search_month_end_planned='';
	$search_day_end_planned='';

	$search_qty_planned='';
	$search_duration_planned='';

	$search_year_start_made='';
	$search_month_start_made='';
	$search_day_start_made='';

	$search_year_end_made='';
	$search_month_end_made='';
	$search_day_end_made='';

	$search_qty_made='';
	$search_duration_made='';

	$viewstatut='';
	$object_statut='';
	$toselect='';
	$search_array_options=array();
	$search_categ_cus=0;

}
if ($object_statut != '') $viewstatut=$object_statut;

if (empty($reshook)) {
	$objectclass='Factory';
	$objectlabel='FactoryOFList';
	$permtoread = $user->rights->factory->lire;
	$permtodelete = $user->rights->factory->supprimer;
	$permtoclose = $user->rights->factory->cloturer;
	$uploaddir = $conf->factory->multidir_output[$conf->entity];
	include DOL_DOCUMENT_ROOT.'/core/actions_massactions.inc.php';
}



/*
 * View
 */

$now=dol_now();

$form = new Form($db);
$formother = new FormOther($db);
$formfile = new FormFile($db);
$companystatic=new Societe($db);
$formcompany=new FormCompany($db);

$help_url='EN:module_factory|FR:module_factory|ES:modulo_factory';
llxHeader('',$langs->trans('Factory'),$help_url);

$sql = 'SELECT';
if ($sall || $search_product_category > 0) $sql = 'SELECT DISTINCT';

$sql.= ' f.*, p.ref as refproduct, e.ref as refentrepot, u.login ';

// Add fields from extrafields
$isExtrafield=(!empty($extrafields->attributes[$object->table_element]['label']) && is_array($extrafields->attributes[$object->table_element]['label']));
if ($isExtrafield) {
	foreach ($extrafields->attributes[$object->table_element]['label'] as $key => $val) 
		$sql.=($extrafields->attributes[$object->table_element]['type'][$key] != 'separate' ? ",ef.".$key.' as options_'.$key : '');
}
// Add fields from hooks
$parameters=array();
$reshook=$hookmanager->executeHooks('printFieldListSelect',$parameters, $object);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;

$sql.= ' FROM '.MAIN_DB_PREFIX.'factory as f';
if ($isExtrafield && count($extrafields->attributes[$object->table_element]['label'])) 
	$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."factory_extrafields as ef on (f.rowid = ef.fk_object)";
if ($search_product_category > 0) 
	$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'categorie_product as cp ON f.fk_product=cp.fk_product';
$sql.= ' LEFT JOIN '.MAIN_DB_PREFIX.'user as u ON f.fk_user_author = u.rowid';
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."entrepot as e ON e.rowid = f.fk_entrepot";
$sql.= " LEFT JOIN ".MAIN_DB_PREFIX."product as p ON p.rowid = f.fk_product";

// We'll need this table joined to the select in order to filter by sale
if ($search_user > 0) {
	$sql.=", ".MAIN_DB_PREFIX."element_contact as c";
	$sql.=", ".MAIN_DB_PREFIX."c_type_contact as tc";
}

// Add fields from hooks
$parameters=array();
$reshook=$hookmanager->executeHooks('printFieldListFrom',$parameters, $object);    // Note that $action and $object may have been modified by hook
$sql.=$hookmanager->resPrint;

$sql.= ' WHERE f.entity IN ('.getEntity('factory').')';

if ($search_ref)         $sql .= natural_search('f.ref', $search_ref);
if ($search_refproduct) $sql .= natural_search('p.ref', $search_refproduct);
if ($search_refentrepot)  $sql .= natural_search('e.ref', $search_refentrepot);

if ($sall) {
	$sql .= natural_search(array_keys($fieldstosearchall), $sall);
}

if ($search_product_category > 0) $sql.=" AND cp.fk_categorie = ".$db->escape($search_product_category);

if ($viewstatut != '' && $viewstatut != '-1'){
	$sql.= ' AND f.fk_statut IN ('.$db->escape($viewstatut).')';
}

$sql.= dol_sql_datefilter(
				"f.datec", 
				$search_day_create, $search_month_create, $search_year_create
);

$sql.= dol_sql_datefilter(
				"f.date_start_planned", 
				$search_day_start_planned, $search_month_start_planned, $search_year_start_planned
);
$sql.= dol_sql_datefilter(
				"f.date_end_planned", 
				$search_day_end_planned, $search_month_end_planned, $search_year_end_planned
);

$sql.= dol_sql_datefilter(
				"f.date_start_made", 
				$search_day_start_made, $search_month_start_made, $search_year_start_made
);
$sql.= dol_sql_datefilter(
				"f.date_end_made", 
				$search_day_end_made, $search_month_end_made, $search_year_end_made
);

if ($search_qty_planned != '') 
	$sql.= natural_search('f.qty_planned', $search_qty_planned, 1);
if ($search_duration_planned != '') 
	$sql.= natural_search('f.duration_planned', $search_duration_planned, 1);

if ($search_qty_made != '') 
	$sql.= natural_search('f.qty_made', $search_qty_made, 1);
if ($search_duration_made != '') 
	$sql.= natural_search('f.duration_made', $search_duration_made, 1);


if ($search_user > 0) {
	$sql.= " AND c.fk_c_type_contact = tc.rowid AND tc.element='factory'";
	$sql.= " AND tc.source='internal' AND c.element_id = p.rowid";
	$sql.= " AND c.fk_socpeople = ".$db->escape($search_user);
}

// Add where from extra fields
if ((int) DOL_VERSION >= 7)
	include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_sql.tpl.php';

// Add where from hooks
$parameters=array();
// Note that $action and $object may have been modified by hook
$reshook=$hookmanager->executeHooks('printFieldListWhere', $parameters, $object);
$sql.=$hookmanager->resPrint;

$sql.= $db->order($sortfield,$sortorder);
$sql.=', f.ref DESC';

// Count total nb of records
$nbtotalofrecords = '';
if (empty($conf->global->MAIN_DISABLE_FULL_SCANLIST)){
	$result = $db->query($sql);
	$nbtotalofrecords = $db->num_rows($result);

	// if total resultset is smaller then paging size (filtering), goto and load page 0
	if (($page * $limit) > $nbtotalofrecords) {
		$page = 0;
		$offset = 0;
	}
}

$sql.= $db->plimit($limit+1, $offset);

$resql=$db->query($sql);
if ($resql) {
	$objectstatic=new Factory($db);
	$userstatic=new User($db);

	$title = $langs->trans('FactoryList');
 	// if ($productid > 0) {
	// 	$productstatic= new product($db);
	// 	$productstatic->fetch($productid);
	// 	$title.= ' - '.$productstatic->ref;
	// 	if (empty($search_refproduct )) 
	// 		$search_refproduct = $productstatic->ref;
	// }
 	// if ($entrepotid > 0) {
	// 	$entrepotstatic= new product($db);
	// 	$entrepotstatic->fetch($entrepotid);
	// 	$title.= ' - '.$entrepotstatic->ref;
	// 	if (empty($search_refentrepot )) 
	// 		$search_refentrepot = $entrepotstatic->ref;
	// }

	$num = $db->num_rows($resql);

	$arrayofselected=is_array($toselect)?$toselect:array();

	$param='&viewstatut='.urlencode($viewstatut);
	if (! empty($contextpage) && $contextpage != $_SERVER["PHP_SELF"]) $param.='&contextpage='.urlencode($contextpage);
	if ($limit > 0 && $limit != $conf->liste_limit) $param.='&limit='.urlencode($limit);
	if ($sall)				 $param.='&sall='.urlencode($sall);

	if ($search_day_create)			$param.='&search_day_create='.urlencode($search_day_create);
	if ($search_month_create)		$param.='&search_month_create='.urlencode($search_month_create);
	if ($search_year_create)		$param.='&search_year_create='.urlencode($search_year_create);

	if ($search_day_start_planned)		$param.='&search_day_start_planned='.urlencode($search_day_start_planned);
	if ($search_month_start_planned)	$param.='&search_month_start_planned='.urlencode($search_month_start_planned);
	if ($search_year_start_planned)		$param.='&search_year_start_planned='.urlencode($search_year_start_planned);
	if ($search_day_end_planned)		$param.='&search_day_end_planned='.urlencode($search_day_en_planned);
	if ($search_month_end_planned)		$param.='&search_month_end_planned='.urlencode($search_month_end_planned);
	if ($search_year_end_planned)		$param.='&search_year_end_planned='.urlencode($search_year_end_planned);

	if ($search_qty_planned)		$param.='&search_qty_planned='.urlencode($search_qty_planned);
	if ($search_duration_planned)	$param.='&search_duration_planned='.urlencode($search_duration_planned);

	if ($search_day_start_made)		$param.='&search_day_start_made='.urlencode($search_day_start_made);
	if ($search_month_start_made)	$param.='&search_month_start_made='.urlencode($search_month_start_made);
	if ($search_year_start_made)	$param.='&search_year_start_made='.urlencode($search_year_start_made);
	if ($search_day_end_made)		$param.='&search_day_end_made='.urlencode($search_day_en_made);
	if ($search_month_end_made)		$param.='&search_month_end_made='.urlencode($search_month_end_made);
	if ($search_year_end_made)		$param.='&search_year_end_made='.urlencode($search_year_end_made);

	if ($search_qty_made)			$param.='&search_qty_made='.urlencode($search_qty_made);
	if ($search_duration_made)		$param.='&search_duration_made='.urlencode($search_duration_made);


	if ($search_ref)         $param.='&search_ref='.urlencode($search_ref);
	if ($search_refproduct)  $param.='&search_refproduct='.urlencode($search_refproduct);
	if ($search_refentrepot) $param.='&search_refentrepot='.urlencode($search_refentrepot);
	if ($optioncss != '')    $param.='&optioncss='.urlencode($optioncss);
	if ($search_product_category != '') $param.='&search_product_category='.$search_product_category;

	// Add $param from extra fields
	if ((int) DOL_VERSION >= 7)
		include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_param.tpl.php';

	$arrayofmassactions =  array();
	// List of mass actions available
	if ((int) DOL_VERSION >= 8) {
		$arrayofmassactions = array(
			// 'presend'=>$langs->trans("SendByMail"),
			'builddoc'=>$langs->trans("PDFMerge"),
		);
		// if ($user->rights->factory->annuler) $arrayofmassactions['cancel']=$langs->trans("Cancel");
	}
	if (in_array($massaction, array('presend','predelete','closed'))) $arrayofmassactions=array();
	$massactionbutton=$form->selectMassAction('', $arrayofmassactions);

	$newcardbutton='';
	if ($user->rights->factory->creer) {
		$newcardbutton='<a class="butActionNew" href="fiche.php?action=create"><span class="valignmiddle">';
		$newcardbutton.= $langs->trans('NewOF').'</span>';
		$newcardbutton.= '<span class="fa fa-plus-circle valignmiddle"></span>';
		$newcardbutton.= '</a>';
	}

	// Lignes des champs de filtre
	print '<form method="POST" id="searchFormList" action="'.$_SERVER["PHP_SELF"].'">';
	if ($optioncss != '') print '<input type="hidden" name="optioncss" value="'.$optioncss.'">';
	print '<input type="hidden" name="token" value="'.$_SESSION['newtoken'].'">';
	print '<input type="hidden" name="formfilteraction" id="formfilteraction" value="list">';
	print '<input type="hidden" name="action" value="list">';
	print '<input type="hidden" name="sortfield" value="'.$sortfield.'">';
	print '<input type="hidden" name="sortorder" value="'.$sortorder.'">';
	print '<input type="hidden" name="page" value="'.$page.'">';
	print '<input type="hidden" name="contextpage" value="'.$contextpage.'">';

	print_barre_liste($title, $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'title_commercial.png', 0, $newcardbutton, '', $limit);

	if ((int) DOL_VERSION >= 7){
		$topicmail="SendFactoryRef";
		$modelmail="factory_send";
		$objecttmp=new Factory($db);
		$trackid='pro'.$object->id;
		include DOL_DOCUMENT_ROOT.'/core/tpl/massactions_pre.tpl.php';
	}
	
	if ($sall) {
		foreach($fieldstosearchall as $key => $val) 
			$fieldstosearchall[$key]=$langs->trans($val);
		print '<div class="divsearchfieldfilter">'.$langs->trans("FilterOnInto", $all);
		print join(', ',$fieldstosearchall).'</div>';
	}

	$i = 0;

	$moreforfilter='';

	// If the user can view products
	if (!empty($conf->categorie->enabled)) {
		include_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
		$moreforfilter.='<div class="divsearchfield">';
		$moreforfilter.=$langs->trans('IncludingProductWithTag'). ': ';
		$cate_arbo = $form->select_all_categories(Categorie::TYPE_PRODUCT, null, 'parent', null, null, 1);
		$moreforfilter.=$form->selectarray('search_product_category', $cate_arbo, $search_product_category, 1, 0, 0, '', 0, 0, 0, 0, 'maxwidth300', 1);
		$moreforfilter.='</div>';
	}
	// If the user can view prospects other than his'
	if ($user->rights->societe->client->voir || $socid)
	{
		$moreforfilter.='<div class="divsearchfield">';
		$moreforfilter.=$langs->trans('LinkedToSpecificUsers'). ': ';
		$moreforfilter.=$form->select_dolusers($search_user, 'search_user', 1, '', 0, '', '', 0, 0, 0, '', 0, '', 'maxwidth200');
	 	$moreforfilter.='</div>';
	}

	$parameters=array();
	$reshook=$hookmanager->executeHooks('printFieldPreListTitle',$parameters, $object);    // Note that $action and $object may have been modified by hook
	if (empty($reshook)) $moreforfilter .= $hookmanager->resPrint;
	else $moreforfilter = $hookmanager->resPrint;

	if (! empty($moreforfilter)) {
		print '<div class="liste_titre liste_titre_bydiv centpercent">';
		print $moreforfilter;
		print '</div>';
	}

	$varpage=empty($contextpage)?$_SERVER["PHP_SELF"]:$contextpage;
	$selectedfields=$form->multiSelectArrayWithCheckbox('selectedfields', $arrayfields, $varpage);	// This also change content of $arrayfields
	$selectedfields.=(count($arrayofmassactions) ? $form->showCheckAddButtons('checkforselect', 1) : '');

	print '<div class="div-table-responsive">';
	print '<table class="tagtable liste'.($moreforfilter?" listwithfilterbefore":"").'">'."\n";

	print '<tr class="liste_titre_filter">';
	if (! empty($arrayfields['p.ref']['checked'])) {
		print '<td class="liste_titre">';
		print '<input class="flat" size="6" type="text" name="search_ref" value="'.dol_escape_htmltag($search_ref).'">';
 		print '</td>';
	}
	if (! empty($arrayfields['f.ref']['checked'])) {
		print '<td class="liste_titre">';
		print '<input class="flat" size="6" type="text" name="search_refproduct" value="'.dol_escape_htmltag($search_refproduct).'">';
		print '</td>';
	}
	if (! empty($arrayfields['e.ref']['checked'])) {
		print '<td class="liste_titre">';
		print '<input class="flat" size="6" type="text" name="search_refentrepot" value="'.dol_escape_htmltag($search_refentrepot).'">';
		print '</td>';
	}

	// Date création
	if (! empty($arrayfields['f.datec']['checked'])) {
		print '<td class="liste_titre nowraponall" align="center">';
		//print $langs->trans('Month').': ';
		if (! empty($conf->global->MAIN_LIST_FILTER_ON_DAY)) 
			print '<input class="flat width25" type="text" maxlength="2" name="search_day_create" value="'.dol_escape_htmltag($search_day_create).'">';
		print '<input class="flat width25 valignmiddle" type="text" maxlength="2" name="search_month_create" value="'.dol_escape_htmltag($search_month_create).'">';
		//print '&nbsp;'.$langs->trans('Year').': ';
		$formother->select_year($search_year_create, 'search_year_create', 1, 20, 5);
		print '</td>';
	}

	if (! empty($arrayfields['f.duration_planned']['checked'])) {
		// durée plannifié
		print '<td class="liste_titre" align="right">';
		print '<input class="flat" type="text" size="5" name="search_duration_planned" value="'.dol_escape_htmltag($search_duration_planned).'">';
		print '</td>';
	}
	if (! empty($arrayfields['f.qty_planned']['checked'])) {
		// Qty Planned
		print '<td class="liste_titre" align="right">';
		print '<input class="flat" type="text" size="5" name="search_qty_planned" value="'.dol_escape_htmltag($search_qty_planned).'">';
		print '</td>';
	}

	// Date debut planifié
	if (! empty($arrayfields['f.date_start_planned']['checked'])) {
		print '<td class="liste_titre nowraponall" align="center">';
		//print $langs->trans('Month').': ';
		if (! empty($conf->global->MAIN_LIST_FILTER_ON_DAY)) 
			print '<input class="flat width25" type="text" maxlength="2" name="search_day_start_planned" value="'.dol_escape_htmltag($search_day_start_planned).'">';
		print '<input class="flat width25 valignmiddle" type="text" maxlength="2" name="search_month_start_planned" value="'.dol_escape_htmltag($search_month_start_planned).'">';
		//print '&nbsp;'.$langs->trans('Year').': ';
		$formother->select_year($search_year_start_planned, 'search_year_start_planned', 1, 20, 5);
		print '</td>';
	}

	if (! empty($arrayfields['f.date_end_planned']['checked'])) {
		print '<td class="liste_titre nowraponall" align="center">';
		//print $langs->trans('Month').': ';
		if (! empty($conf->global->MAIN_LIST_FILTER_ON_DAY)) 
			print '<input class="flat width25" type="text" maxlength="2" name="search_day_end_planned" value="'.dol_escape_htmltag($search_day_end_planned).'">';
		print '<input class="flat width25 valignmiddle" type="text" maxlength="2" name="search_month_end_planned" value="'.dol_escape_htmltag($search_month_end_planned).'">';
		//print '&nbsp;'.$langs->trans('Year').': ';
		$formother->select_year($search_year_end_planned, 'search_year_end_planned', 1, 20, 5);
		print '</td>';
	}


	if (! empty($arrayfields['f.duration_made']['checked'])) {
		// durée réalisé
		print '<td class="liste_titre" align="right">';
		print '<input class="flat" type="text" size="5" name="search_duration_made" value="'.dol_escape_htmltag($search_duration_made).'">';
		print '</td>';
	}
	
	if (! empty($arrayfields['f.qty_made']['checked'])) {
		// Qty réalisé
		print '<td class="liste_titre" align="right">';
		print '<input class="flat" type="text" size="5" name="search_qty_made" value="'.dol_escape_htmltag($search_qty_made).'">';
		print '</td>';
	}

	// Date debut planifié
	if (! empty($arrayfields['f.date_start_made']['checked'])) {
		print '<td class="liste_titre nowraponall" align="center">';
		//print $langs->trans('Month').': ';
		if (! empty($conf->global->MAIN_LIST_FILTER_ON_DAY)) 
			print '<input class="flat width25" type="text" maxlength="2" name="search_day_start_made" value="'.dol_escape_htmltag($search_day_start_made).'">';
		print '<input class="flat width25 valignmiddle" type="text" maxlength="2" name="search_month_start_made" value="'.dol_escape_htmltag($search_month_start_made).'">';
		//print '&nbsp;'.$langs->trans('Year').': ';
		$formother->select_year($search_year_start_made, 'search_year_start_made', 1, 20, 5);
		print '</td>';
	}

	if (! empty($arrayfields['f.date_end_made']['checked'])) {
		print '<td class="liste_titre nowraponall" align="center">';
		//print $langs->trans('Month').': ';
		if (! empty($conf->global->MAIN_LIST_FILTER_ON_DAY)) 
			print '<input class="flat width25" type="text" maxlength="2" name="search_day_end_made" value="'.dol_escape_htmltag($search_day_end_made).'">';
		print '<input class="flat width25 valignmiddle" type="text" maxlength="2" name="search_month_end_made" value="'.dol_escape_htmltag($search_month_end_made).'">';
		//print '&nbsp;'.$langs->trans('Year').': ';
		$formother->select_year($search_year_end_made, 'search_year_end_made', 1, 20, 5);
		print '</td>';
	}

	// Extra fields
	if ((int) DOL_VERSION >= 7)
		include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_input.tpl.php';

	// Fields from hook
	$parameters=array('arrayfields'=>$arrayfields);
	$reshook=$hookmanager->executeHooks('printFieldListOption',$parameters, $object);    // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;

	// Controling
	if (! empty($arrayfields['f.controling']['checked'])) {
		print '<td class="liste_titre maxwidthonsmartphone" align="right">';
		//$object->selectControlingStatus($search_controling, 1, 0, 1, 'search_controling');
		print '</td>';
	}

	// Status
	if (! empty($arrayfields['f.fk_statut']['checked'])) {
		print '<td class="liste_titre maxwidthonsmartphone" align="right">';
		$object->selectFactoryStatus($viewstatut, 1, 0, 1, 'search_statut');
		print '</td>';
	}

	// Action column
	print '<td class="liste_titre" align="middle">';
	$searchpicto=$form->showFilterButtons();
	print $searchpicto;
	print '</td>';

	print "</tr>\n";


	// Fields title
	print '<tr class="liste_titre">';
	if (! empty($arrayfields['f.ref']['checked']))					print_liste_field_titre($arrayfields['f.ref']['label'],$_SERVER["PHP_SELF"],'f.ref','',$param,'',$sortfield,$sortorder);
	if (! empty($arrayfields['p.ref']['checked']))					print_liste_field_titre($arrayfields['p.ref']['label'],$_SERVER["PHP_SELF"],'p.ref','',$param,'',$sortfield,$sortorder);
	if (! empty($arrayfields['e.ref']['checked']))					print_liste_field_titre($arrayfields['e.ref']['label'],$_SERVER["PHP_SELF"],'e.ref','',$param,'',$sortfield,$sortorder);

	if (! empty($arrayfields['f.datec']['checked']))				print_liste_field_titre($arrayfields['f.datec']['label'],$_SERVER["PHP_SELF"],'f.datec','',$param, 'align="center"',$sortfield,$sortorder);

	if (! empty($arrayfields['f.duration_planned']['checked']))		print_liste_field_titre($arrayfields['f.duration_planned']['label'],$_SERVER["PHP_SELF"],'f.duration_planned','',$param, 'align="center"',$sortfield,$sortorder);
	if (! empty($arrayfields['f.qty_planned']['checked']))			print_liste_field_titre($arrayfields['f.qty_planned']['label'],$_SERVER["PHP_SELF"],'f.qty_planned','',$param, 'align="center"',$sortfield,$sortorder);
	if (! empty($arrayfields['f.date_start_planned']['checked']))	print_liste_field_titre($arrayfields['f.date_start_planned']['label'],$_SERVER["PHP_SELF"],'f.date_start_planned','',$param, 'align="center"',$sortfield,$sortorder);
	if (! empty($arrayfields['f.date_end_planned']['checked']))		print_liste_field_titre($arrayfields['f.date_end_planned']['label'],$_SERVER["PHP_SELF"],'f.date_end_planned','',$param, 'align="center"',$sortfield,$sortorder);

	if (! empty($arrayfields['f.duration_made']['checked']))		print_liste_field_titre($arrayfields['f.duration_made']['label'],$_SERVER["PHP_SELF"],'f.duration_made','',$param, 'align="center"',$sortfield,$sortorder);
	if (! empty($arrayfields['f.qty_made']['checked']))				print_liste_field_titre($arrayfields['f.qty_made']['label'],$_SERVER["PHP_SELF"],'f.qty_made','',$param, 'align="center"',$sortfield,$sortorder);
	if (! empty($arrayfields['f.date_start_made']['checked']))		print_liste_field_titre($arrayfields['f.date_start_made']['label'],$_SERVER["PHP_SELF"],'f.date_start_made','',$param, 'align="center"',$sortfield,$sortorder);
	if (! empty($arrayfields['f.date_end_made']['checked']))		print_liste_field_titre($arrayfields['f.date_end_made']['label'],$_SERVER["PHP_SELF"],'f.date_end_made','',$param, 'align="center"',$sortfield,$sortorder);

	if (! empty($arrayfields['u.login']['checked']))				print_liste_field_titre($arrayfields['u.login']['label'],$_SERVER["PHP_SELF"],'u.login','',$param,'align="center"',$sortfield,$sortorder);
	// Extra fields
	if ((int) DOL_VERSION >= 7)
		include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_search_title.tpl.php';
	
	// Hook fields
	$parameters=array('arrayfields'=>$arrayfields,'param'=>$param,'sortfield'=>$sortfield,'sortorder'=>$sortorder);
	$reshook=$hookmanager->executeHooks('printFieldListTitle',$parameters, $object);    // Note that $action and $object may have been modified by hook
	print $hookmanager->resPrint;
	
	// Controling
	if (! empty($arrayfields['f.controling']['checked'])) 
		print_liste_field_titre($arrayfields['f.controling']['label'],"","","","",'align="right"');
	
	

	if (! empty($arrayfields['f.fk_statut']['checked'])) 
		print_liste_field_titre($arrayfields['f.fk_statut']['label'],$_SERVER["PHP_SELF"],"f.fk_statut","",$param,'align="right"',$sortfield,$sortorder);
	print_liste_field_titre($selectedfields, $_SERVER["PHP_SELF"],"",'','','align="center"',$sortfield,$sortorder,'maxwidthsearch ');
	print '</tr>'."\n";

	$now = dol_now();
	$i=0;
	$totalarray=array();
	$totalarray['nbfield']=0;
	while ($i < min($num,$limit)) {
		$obj = $db->fetch_object($resql);

		$objectstatic->fetch($obj->rowid);
		//$objectstatic->ref=$obj->ref;

		print '<tr class="oddeven">';

		if (! empty($arrayfields['f.ref']['checked'])) {
			print '<td class="nowrap">';

			print '<table class="nobordernopadding"><tr class="nocellnopadd">';
			// Picto + Ref
			print '<td class="nobordernopadding nowrap">';
			print $objectstatic->getNomUrl(1, '', '', 0, 1);
			print '</td>';
			// Warning
			$warnornote='';
			if (! empty($obj->note_private)) {
				$warnornote.=($warnornote?' ':'');
				$warnornote.= '<span class="note">';
				$warnornote.= '<a href="note.php?id='.$obj->rowid.'">'.img_picto($langs->trans("ViewPrivateNote"),'object_generic').'</a>';
				$warnornote.= '</span>';
			}
			if ($warnornote) {
				print '<td style="min-width: 20px" class="nobordernopadding nowrap">';
				print $warnornote;
				print '</td>';
			}
			// Other picto tool
			print '<td width="16" align="right" class="nobordernopadding">';
			$filename=dol_sanitizeFileName($obj->ref);
			$filedir=$conf->factory->multidir_output[$obj->entity] . '/' . dol_sanitizeFileName($obj->ref);
			$urlsource=$_SERVER['PHP_SELF'].'?id='.$obj->rowid;
			print $formfile->getDocumentsLink($objectstatic->element, $filename, $filedir);
			print '</td></tr></table>';

			print "</td>\n";
			if (! $i) $totalarray['nbfield']++;
		}

		if (! empty($arrayfields['p.ref']['checked'])) {
			// Customer ref
			print '<td class="nocellnopadd nowrap">';
			$product->fetch($obj->fk_product);
			print $product->getNomUrl(1);
			print '</td>';
			if (! $i) $totalarray['nbfield']++;
		}

		if (! empty($arrayfields['e.ref']['checked'])) {
			// entrepot ref
			print '<td class="nocellnopadd nowrap">';
			if ($obj->fk_entrepot) {
				$entrepot->fetch($obj->fk_entrepot);
				print $entrepot->getNomUrl(1);
			}
			print '</td>';
			if (! $i) $totalarray['nbfield']++;
		}

		// Date OF
		if (! empty($arrayfields['f.datec']['checked'])) {
			print '<td align="center">';
			print dol_print_date($db->jdate($obj->datec), 'day');
			print "</td>\n";
			if (! $i) $totalarray['nbfield']++;
		}

		// duration planned
		if (! empty($arrayfields['f.duration_planned']['checked'])) {
			print '<td align="center">';
			print convertSecondToTime($obj->duration_planned, 'allhourmin');
			print "</td>\n";
			if (! $i) $totalarray['nbfield']++;
		}

		// qty planned
		if (! empty($arrayfields['f.qty_planned']['checked'])) {
			print '<td align="center">';
			print $obj->qty_planned;
			print "</td>\n";
			if (! $i) $totalarray['nbfield']++;
		}


		// Date start planned
		if (! empty($arrayfields['f.date_start_planned']['checked'])) {
			if ($obj->date_start_planned) {
				print '<td align="center">'.dol_print_date($db->jdate($obj->date_start_planned),'day');
				print '</td>';
			} else {
				print '<td>&nbsp;</td>';
			}
			if (! $i) $totalarray['nbfield']++;
		}

		if (! empty($arrayfields['f.date_end_planned']['checked'])) {
			if ($obj->date_end_planned) {
				print '<td align="center">'.dol_print_date($db->jdate($obj->date_end_planned),'day');
				print '</td>';
			} else {
				print '<td>&nbsp;</td>';
			}
			if (! $i) $totalarray['nbfield']++;
		}

		// duration made
		if (! empty($arrayfields['f.duration_made']['checked'])) {
			print '<td align="center">';
			print convertSecondToTime($obj->duration_made, 'allhourmin');
			print "</td>\n";
			if (! $i) $totalarray['nbfield']++;
		}

		// qty made
		if (! empty($arrayfields['f.qty_made']['checked'])) {
			print '<td align="center">';
			print $obj->qty_made;
			print "</td>\n";
			if (! $i) $totalarray['nbfield']++;
		}

		// Date start made
		if (! empty($arrayfields['f.date_start_made']['checked'])) {
			if ($obj->date_start_made) {
				print '<td align="center">'.dol_print_date($db->jdate($obj->date_start_made),'day');
				print '</td>';
			} else {
				print '<td>&nbsp;</td>';
			}
			if (! $i) $totalarray['nbfield']++;
		}

		if (! empty($arrayfields['f.date_end_made']['checked'])) {
			if ($obj->date_end_made) {
				print '<td align="center">'.dol_print_date($db->jdate($obj->date_end_made),'day');
				print '</td>';
			} else {
				print '<td>&nbsp;</td>';
			}
			if (! $i) $totalarray['nbfield']++;
		}

		
		// Author
		if (! empty($arrayfields['u.login']['checked'])) {
			$userstatic->id=$obj->fk_user_author;
			$userstatic->login=$obj->login;
			print '<td align="center">';
			if ($userstatic->id) print $userstatic->getLoginUrl(1);
			else print '&nbsp;';
			print "</td>\n";
			if (! $i) $totalarray['nbfield']++;
		}

		// Extra fields
		if ((int) DOL_VERSION >= 7)
			include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_list_print_fields.tpl.php';

		// Fields from hook
		$parameters=array('arrayfields'=>$arrayfields, 'obj'=>$obj);
		$reshook=$hookmanager->executeHooks('printFieldListValue',$parameters, $object);    // Note that $action and $object may have been modified by hook
		print $hookmanager->resPrint;

		// Controling
		if (! empty($arrayfields['f.controling']['checked'])) {
			print '<td align="right" class="nowrap">';
			// on affiche l'info de controle sur les quantités
			print $objectstatic->controlingInfos();
			print '</td>';
			if (! $i) $totalarray['nbfield']++;
		}


		// Status
		if (! empty($arrayfields['f.fk_statut']['checked']))
		{
			print '<td align="right" class="nowrap">'.$objectstatic->LibStatut($obj->fk_statut,5).'</td>';
			if (! $i) $totalarray['nbfield']++;
		}
		// Action column
		print '<td class="nowrap" align="center">';
		// If we are in select mode (massactionbutton defined) or if we have already selected and sent an action ($massaction) defined
		if ($massactionbutton || $massaction)
		{
			$selected=0;
			if (in_array($obj->rowid, $arrayofselected)) $selected=1;
			print '<input id="cb'.$obj->rowid.'" class="flat checkforselect" type="checkbox" name="toselect[]" value="'.$obj->rowid.'"'.($selected?' checked="checked"':'').'>';
		}
		print '</td>';
		if (! $i) $totalarray['nbfield']++;

		print "</tr>\n";

		$i++;
	}

	// Show total line
		if (isset($totalarray['totalhtfield'])
 	   || isset($totalarray['totalvatfield'])
 	   || isset($totalarray['totalttcfield'])
 	   || isset($totalarray['totalamfield'])
 	   || isset($totalarray['totalrtpfield'])
 	   )
	{
		print '<tr class="liste_total">';
		$i=0;
		while ($i < $totalarray['nbfield']) {
			$i++;
			if ($i == 1) {
				if ($num < $limit && empty($offset)) 
					print '<td align="left">'.$langs->trans("Total").'</td>';
				else 
					print '<td align="left">'.$langs->trans("Totalforthispage").'</td>';
			}
			elseif ($totalarray['totalhtfield'] == $i) 
				print '<td align="right">'.price($totalarray['totalht']).'</td>';
			elseif ($totalarray['totalvatfield'] == $i) 
				print '<td align="right">'.price($totalarray['totalvat']).'</td>';
			elseif ($totalarray['totalttcfield'] == $i) 
				print '<td align="right">'.price($totalarray['totalttc']).'</td>';
			else 
				print '<td></td>';
		}
		print '</tr>';
	}

	$db->free($resql);

	$parameters=array('arrayfields'=>$arrayfields, 'sql'=>$sql);
	// Note that $action and $object may have been modified by hook
	$reshook=$hookmanager->executeHooks('printFieldListFooter',$parameters, $object);
	print $hookmanager->resPrint;

	print '</table>'."\n";
	print '</div>'."\n";

	print '</form>'."\n";

	$hidegeneratedfilelistifempty=1;
	if ($massaction == 'builddoc' || $action == 'remove_file' || $show_files) 
		$hidegeneratedfilelistifempty=0;

	// Show list of available documents
	$urlsource=$_SERVER['PHP_SELF'].'?sortfield='.$sortfield.'&sortorder='.$sortorder;
	$urlsource.=str_replace('&amp;','&',$param);

	$filedir=$diroutputmassaction;
	$genallowed=$user->rights->factory->lire;
	$delallowed=$user->rights->factory->creer;

	print $formfile->showdocuments(
					'massfilesarea_factory', '', $filedir, $urlsource, 0, $delallowed,
					'', 1, 1, 0, 48, 1, $param, $title, '', '', '',
					null, $hidegeneratedfilelistifempty
	);
} else {
	dol_print_error($db);
}

// End of page
llxFooter();
$db->close();

