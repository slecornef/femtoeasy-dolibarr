<?php
require_once DOL_DOCUMENT_ROOT.'/core/modules/commande/modules_commande.php';

class mod_commande_marbre_fe extends ModeleNumRefCommandes
{
	public $version = 'dolibarr'; // 'development', 'experimental', 'dolibarr'

	public $error = '';

	public $name = 'Marbre FE';

	public function __construct()
	{
		global $conf, $mysoc;

		if ((float) $conf->global->MAIN_VERSION_LAST_INSTALL >= 16.0 && $mysoc->country_code != 'FR') {
			$this->prefix = 'SO'; // We use correct standard code "SO = Sale Order"
		}
	}

	public function info()
	{
		return "Numérotation en utilisant un préfixe dépendant du type de commande (marbre modifié par Femto Easy)";
	}

	public function getExample()
	{
		return "SAV2407-0001";
	}

	public function canBeActivated()
	{
		return true;
	}

	public function getNextValue($objsoc, $object)
	{
		global $db;
        
		$prefix = '?';
		
		switch($object->array_options['options_statutspecial']) {
		    case 1: // Commande
		    case 2: // Précommande
		        $prefix = 'CO';
		        break;
		    case 3: // SAV sou garantie
		    case 4: // SAV hors garantie
		        $prefix = 'SAV';
		        break;
		    case 5: // Démo
		        $prefix = 'DEM';
		        break;
		    default:
		        dol_syslog("mod_commande_marbre_fe::getNextValue wrong switch", LOG_DEBUG);
		        return -1;
		}
		
		// First, we get the max value
		$posindice = strlen($prefix) + 6;
		$sql = "SELECT MAX(CAST(SUBSTRING(ref FROM $posindice) AS SIGNED)) as max";
		$sql .= " FROM " . MAIN_DB_PREFIX . "commande";
		$sql .= " WHERE ref LIKE '" . $db->escape($prefix) . "____-%'";
		$sql .= " AND entity IN (" . getEntity('ordernumber', 1, $object) . ")";

		$resql = $db->query($sql);
		if ($resql) {
			$obj = $db->fetch_object($resql);
			if ($obj) {
				$max = intval($obj->max);
			} else {
				$max = 0;
			}
		} else {
			dol_syslog("mod_commande_marbre_fe::getNextValue", LOG_DEBUG);
			return -1;
		}

		//$date=time();
		$date = $object->date;
		$yymm = strftime("%y%m", $date);

		if ($max >= (pow(10, 4) - 1)) {
			$num = $max + 1; // If counter > 9999, we do not format on 4 chars, we take number as it is
		} else {
			$num = sprintf("%04s", $max + 1);
		}

		dol_syslog("mod_commande_marbre_fe::getNextValue return " . $prefix . $yymm . "-" . $num);
		return $prefix . $yymm . "-" . $num;
	}

	public function commande_get_num($objsoc, $objforref)
	{
		return $this->getNextValue($objsoc, $objforref);
	}
}
