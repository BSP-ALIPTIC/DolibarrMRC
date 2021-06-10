<?php
/* Copyright (C) 2005       Matthieu Valleton	<mv@seeschloss.org>
 * Copyright (C) 2006-2020  Laurent Destailleur  <eldy@users.sourceforge.net>
 * Copyright (C) 2007       Patrick Raguin		<patrick.raguin@gmail.com>
 * Copyright (C) 2005-2012  Regis Houssin		<regis.houssin@inodbox.com>
 * Copyright (C) 2015       Raphaël Doursenaud  <rdoursenaud@gpcsolutions.fr>
 * Copyright (C) 2020		Tobias Sekan		<tobias.sekan@startmail.com>
 * Copyright (C) 2020		Josep Lluís Amador  <joseplluis@lliuretic.cat>
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 *       \file       htdocs/categories/viewcat.php
 *       \ingroup    category
 *       \brief      Page to show a category card
 */

require '../main.inc.php';
require_once DOL_DOCUMENT_ROOT.'/categories/class/categorie.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/lib/categories.lib.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/extrafields.class.php';
require_once DOL_DOCUMENT_ROOT.'/core/class/html.formother.class.php';

// Load translation files required by the page
$langs->load("categories");

$id         = GETPOST('id', 'int');
$label      = GETPOST('label', 'alpha');
$removeelem = GETPOST('removeelem', 'int');
$elemid     = GETPOST('elemid', 'int');

$action     = GETPOST('action', 'aZ09') ?GETPOST('action', 'aZ09') : 'view'; // The action 'add', 'create', 'edit', 'update', 'view', ...
$massaction = GETPOST('massaction', 'alpha'); // The bulk action (combo box choice into lists)
$show_files = GETPOST('show_files', 'int'); // Show files area generated by bulk actions ?
$confirm    = GETPOST('confirm', 'alpha'); // Result of a confirmation
$cancel     = GETPOST('cancel', 'alpha'); // We click on a Cancel button
$toselect   = GETPOST('toselect', 'array'); // Array of ids of elements selected into a list
$contextpage = GETPOST('contextpage', 'aZ') ?GETPOST('contextpage', 'aZ') : 'categorylist'; // To manage different context of search
$backtopage = GETPOST('backtopage', 'alpha'); // Go back to a dedicated page
$optioncss = GETPOST('optioncss', 'aZ'); // Option for the css output (always '' except when 'print')


// Load variable for pagination
$limit = GETPOST('limit', 'int') ?GETPOST('limit', 'int') : $conf->liste_limit;
$sortfield = GETPOST('sortfield', 'aZ09comma');
$sortorder = GETPOST('sortorder', 'aZ09comma');
$page = GETPOSTISSET('pageplusone') ? (GETPOST('pageplusone') - 1) : GETPOST("page", 'int');
if (empty($page) || $page == -1 || GETPOST('button_search', 'alpha') || GETPOST('button_removefilter', 'alpha') || (empty($toselect) && $massaction === '0')) {
	$page = 0;
}     // If $page is not defined, or '' or -1 or if we click on clear filters or if we select empty mass action
$offset = $limit * $page;
$pageprev = $page - 1;
$pagenext = $page + 1;

if ($id == "" && $label == "") {
	dol_print_error('', 'Missing parameter id');
	exit();
}

// Security check
$result = restrictedArea($user, 'categorie', $id, '&category');

$object = new Categorie($db);
$result = $object->fetch($id, $label);
if ($result <= 0) {
	dol_print_error($db, $object->error); exit;
}

$type = $object->type;
if (is_numeric($type)) {
	$type = Categorie::$MAP_ID_TO_CODE[$type]; // For backward compatibility
}

$extrafields = new ExtraFields($db);
$extrafields->fetch_name_optionals_label($object->table_element);

// Initialize technical object to manage hooks. Note that conf->hooks_modules contains array array
$hookmanager->initHooks(array('categorycard', 'globalcard'));

/*
 *	Actions
 */

if ($confirm == 'no') {
	if ($backtopage) {
		header("Location: ".$backtopage);
		exit;
	}
}

$parameters = array();
$reshook = $hookmanager->executeHooks('doActions', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
// Remove element from category
if ($id > 0 && $removeelem > 0) {
	if ($type == Categorie::TYPE_PRODUCT && ($user->rights->produit->creer || $user->rights->service->creer)) {
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$tmpobject = new Product($db);
		$result = $tmpobject->fetch($removeelem);
		$elementtype = 'product';
	} elseif ($type == Categorie::TYPE_SUPPLIER && $user->rights->societe->creer) {
		$tmpobject = new Societe($db);
		$result = $tmpobject->fetch($removeelem);
		$elementtype = 'supplier';
	} elseif ($type == Categorie::TYPE_CUSTOMER && $user->rights->societe->creer) {
		$tmpobject = new Societe($db);
		$result = $tmpobject->fetch($removeelem);
		$elementtype = 'customer';
	} elseif ($type == Categorie::TYPE_MEMBER && $user->rights->adherent->creer) {
		require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';
		$tmpobject = new Adherent($db);
		$result = $tmpobject->fetch($removeelem);
		$elementtype = 'member';
	} elseif ($type == Categorie::TYPE_CONTACT && $user->rights->societe->creer) {
		require_once DOL_DOCUMENT_ROOT.'/contact/class/contact.class.php';
		$tmpobject = new Contact($db);
		$result = $tmpobject->fetch($removeelem);
		$elementtype = 'contact';
	} elseif ($type == Categorie::TYPE_ACCOUNT && $user->rights->banque->configurer) {
		require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
		$tmpobject = new Account($db);
		$result = $tmpobject->fetch($removeelem);
		$elementtype = 'account';
	} elseif ($type == Categorie::TYPE_PROJECT && $user->rights->projet->creer) {
		require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
		$tmpobject = new Project($db);
		$result = $tmpobject->fetch($removeelem);
		$elementtype = 'project';
	} elseif ($type == Categorie::TYPE_USER && $user->rights->user->user->creer) {
		require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
		$tmpobject = new User($db);
		$result = $tmpobject->fetch($removeelem);
		$elementtype = 'user';
	}

	$result = $object->del_type($tmpobject, $elementtype);
	if ($result < 0) {
		dol_print_error('', $object->error);
	}
}

if ($user->rights->categorie->supprimer && $action == 'confirm_delete' && $confirm == 'yes') {
	if ($object->delete($user) >= 0) {
		if ($backtopage) {
			header("Location: ".$backtopage);
			exit;
		} else {
			header("Location: ".DOL_URL_ROOT.'/categories/index.php?type='.$type);
			exit;
		}
	} else {
		setEventMessages($object->error, $object->errors, 'errors');
	}
}

if ($elemid && $action == 'addintocategory' &&
	(($type == Categorie::TYPE_PRODUCT && ($user->rights->produit->creer || $user->rights->service->creer)) ||
	 ($type == Categorie::TYPE_CUSTOMER && $user->rights->societe->creer) ||
	 ($type == Categorie::TYPE_SUPPLIER && $user->rights->societe->creer)
   )) {
	if ($type == Categorie::TYPE_PRODUCT) {
		require_once DOL_DOCUMENT_ROOT.'/product/class/product.class.php';
		$newobject = new Product($db);
		$elementtype = 'product';
	} elseif ($type == Categorie::TYPE_CUSTOMER) {
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$newobject = new Societe($db);
		$elementtype = 'customer';
	} elseif ($type == Categorie::TYPE_SUPPLIER) {
		require_once DOL_DOCUMENT_ROOT.'/societe/class/societe.class.php';
		$newobject = new Societe($db);
		$elementtype = 'supplier';
	}
	$result = $newobject->fetch($elemid);

	// TODO Add into categ
	$result = $object->add_type($newobject, $elementtype);
	if ($result >= 0) {
		setEventMessages($langs->trans("WasAddedSuccessfully", $newobject->ref), null, 'mesgs');
	} else {
		if ($cat->error == 'DB_ERROR_RECORD_ALREADY_EXISTS') {
			setEventMessages($langs->trans("ObjectAlreadyLinkedToCategory"), null, 'warnings');
		} else {
			setEventMessages($object->error, $object->errors, 'errors');
		}
	}
}


/*
 * View
 */

$form = new Form($db);
$formother = new FormOther($db);

$arrayofjs = array('/includes/jquery/plugins/jquerytreeview/jquery.treeview.js', '/includes/jquery/plugins/jquerytreeview/lib/jquery.cookie.js');
$arrayofcss = array('/includes/jquery/plugins/jquerytreeview/jquery.treeview.css');

$help_url = '';

llxHeader("", $langs->trans("Categories"), $help_url, '', 0, 0, $arrayofjs, $arrayofcss);

$title = Categorie::$MAP_TYPE_TITLE_AREA[$type];

$head = categories_prepare_head($object, $type);
print dol_get_fiche_head($head, 'card', $langs->trans($title), -1, 'category');

$backtolist = (GETPOST('backtolist') ? GETPOST('backtolist') : DOL_URL_ROOT.'/categories/index.php?leftmenu=cat&type='.urlencode($type));
$linkback = '<a href="'.dol_sanitizeUrl($backtolist).'">'.$langs->trans("BackToList").'</a>';
$object->next_prev_filter = ' type = '.$object->type;
$object->ref = $object->label;
$morehtmlref = '<br><div class="refidno"><a href="'.DOL_URL_ROOT.'/categories/index.php?leftmenu=cat&type='.urlencode($type).'">'.$langs->trans("Root").'</a> >> ';
$ways = $object->print_all_ways(" &gt;&gt; ", '', 1);
foreach ($ways as $way) {
	$morehtmlref .= $way."<br>\n";
}
$morehtmlref .= '</div>';

dol_banner_tab($object, 'label', $linkback, ($user->socid ? 0 : 1), 'label', 'label', $morehtmlref, '&type='.urlencode($type), 0, '', '', 1);


/*
 * Confirmation suppression
 */

if ($action == 'delete') {
	if ($backtopage) {
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&type='.$type.'&backtopage='.urlencode($backtopage), $langs->trans('DeleteCategory'), $langs->trans('ConfirmDeleteCategory'), 'confirm_delete', '', '', 2);
	} else {
		print $form->formconfirm($_SERVER["PHP_SELF"].'?id='.$object->id.'&type='.$type, $langs->trans('DeleteCategory'), $langs->trans('ConfirmDeleteCategory'), 'confirm_delete', '', '', 1);
	}
}

print '<br>';

print '<div class="fichecenter">';
print '<div class="underbanner clearboth"></div>';
print '<table class="border centpercent tableforfield">';

// Description
print '<tr><td class="titlefield notopnoleft tdtop">';
print $langs->trans("Description").'</td><td>';
print dol_htmlentitiesbr($object->description);
print '</td></tr>';

// Color
print '<tr><td class="notopnoleft">';
print $langs->trans("Color").'</td><td>';
print $formother->showColor($object->color);
print '</td></tr>';

// Other attributes
include DOL_DOCUMENT_ROOT.'/core/tpl/extrafields_view.tpl.php';

print '</table>';
print '</div>';

print dol_get_fiche_end();


/*
 * Boutons actions
 */

print "<div class='tabsAction'>\n";
$reshook = $hookmanager->executeHooks('addMoreActionsButtons', $parameters, $object, $action); // Note that $action and $object may have been modified by some hooks
if ($reshook < 0) {
	setEventMessages($hookmanager->error, $hookmanager->errors, 'errors');
}
if (empty($reshook)) {
	if ($user->rights->categorie->creer) {
		$socid = ($object->socid ? "&socid=".$object->socid : "");
		print '<a class="butAction" href="edit.php?id='.$object->id.$socid.'&type='.$type.'">'.$langs->trans("Modify").'</a>';
	}

	if ($user->rights->categorie->supprimer) {
		print '<a class="butActionDelete" href="'.$_SERVER["PHP_SELF"].'?action=delete&token='.newToken().'&id='.$object->id.'&type='.$type.'&backtolist='.urlencode($backtolist).'">'.$langs->trans("Delete").'</a>';
	}
}

print "</div>";

$newcardbutton = '';
if (!empty($user->rights->categorie->creer)) {
	$link = DOL_URL_ROOT.'/categories/card.php';
	$link .= '?action=create';
	$link .= '&type='.$type;
	$link .= '&catorigin='.$object->id;
	$link .= '&backtopage='.urlencode($_SERVER["PHP_SELF"].'?type='.$type.'&id='.$id);

	$newcardbutton = '<div class="right">';
	$newcardbutton .= dolGetButtonTitle($langs->trans('NewCategory'), '', 'fa fa-plus-circle', $link);
	$newcardbutton .= '</div>';
}


/*
 * Sub-category tree view of this category
 */

print '<div class="fichecenter">';

print load_fiche_titre($langs->trans("SubCats"), $newcardbutton, 'object_category');


print '<table class="liste nohover" width="100%">';

print '<tr class="liste_titre">';
print '<td>'.$langs->trans("SubCats").'</td>';
print '<td></td>';
print '<td class="right">';

if (!empty($conf->use_javascript_ajax)) {
	print '<div id="iddivjstreecontrol">';
	print '<a class="notasortlink" href="#">'.img_picto('', 'folder').' '.$langs->trans("UndoExpandAll").'</a>';
	print " | ";
	print '<a class="notasortlink" href="#">'.img_picto('', 'folder-open').' '.$langs->trans("ExpandAll").'</a>';
	print '</div>';
}

print '</td>';
print '</tr>';

$cats = $object->get_filles();
if ($cats < 0) {
	dol_print_error($db, $object->error, $object->errors);
} elseif (count($cats) < 1) {
	print '<tr class="oddeven">';
	print '<td colspan="3" class="opacitymedium">'.$langs->trans("NoSubCat").'</td>';
	print '</tr>';
} else {
	$categstatic = new Categorie($db);

	$fulltree = $categstatic->get_full_arbo($type, $object->id, 1);

	// Load possible missing includes
	if ($conf->global->CATEGORY_SHOW_COUNTS) {
		if ($type == Categorie::TYPE_MEMBER) {
			require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';
		}
		if ($type == Categorie::TYPE_ACCOUNT) {
			require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';
		}
		if ($type == Categorie::TYPE_PROJECT) {
			require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';
		}
		if ($type == Categorie::TYPE_USER) {
			require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';
		}
	}

	// Define data (format for treeview)
	$data = array();
	$data[] = array('rowid'=>0, 'fk_menu'=>-1, 'title'=>"racine", 'mainmenu'=>'', 'leftmenu'=>'', 'fk_mainmenu'=>'', 'fk_leftmenu'=>'');
	foreach ($fulltree as $key => $val) {
		$categstatic->id = $val['id'];
		$categstatic->ref = $val['label'];
		$categstatic->color = $val['color'];
		$categstatic->type = $type;
		$desc = dol_htmlcleanlastbr($val['description']);

		$counter = '';
		if ($conf->global->CATEGORY_SHOW_COUNTS) {
			// we need only a count of the elements, so it is enough to consume only the id's from the database
			$elements = $type == Categorie::TYPE_ACCOUNT
				? $categstatic->getObjectsInCateg("account", 1)			// Categorie::TYPE_ACCOUNT is "bank_account" instead of "account"
				: $categstatic->getObjectsInCateg($type, 1);

			$counter = "<td class='left' width='40px;'>".(is_countable($elements) ? count($elements) : '0')."</td>";
		}

		$color = $categstatic->color ? ' style="background: #'.sprintf("%06s", $categstatic->color).';"' : ' style="background: #bbb"';
		$li = $categstatic->getNomUrl(1, '', 60, '&backtolist='.urlencode($_SERVER["PHP_SELF"].'?id='.$id.'&type='.$type));

		$entry = '<table class="nobordernopadding centpercent">';
		$entry .= '<tr>';

		$entry .= '<td>';
		$entry .= '<span class="noborderoncategories" '.$color.'>'.$li.'</span>';
		$entry .= '</td>';

		$entry .= $counter;

		$entry .= '<td class="right" width="20px;">';
		$entry .= '<a href="'.DOL_URL_ROOT.'/categories/viewcat.php?id='.$val['id'].'&type='.$type.'&backtolist='.urlencode($_SERVER["PHP_SELF"].'?id='.$id.'&type='.$type).'">'.img_view().'</a>';
		$entry .= '</td>';
		$entry .= '<td class="right" width="20px;">';
		$entry .= '<a class="editfielda" href="'.DOL_URL_ROOT.'/categories/edit.php?id='.$val['id'].'&type='.$type.'&backtopage='.urlencode($_SERVER["PHP_SELF"].'?id='.$id.'&type='.$type).'">'.img_edit().'</a>';
		$entry .= '</td>';
		$entry .= '<td class="right" width="20px;">';
		$entry .= '<a class="deletefilelink" href="'.DOL_URL_ROOT.'/categories/viewcat.php?action=delete&token='.newToken().'&id='.$val['id'].'&type='.$type.'&backtopage='.urlencode($_SERVER["PHP_SELF"].'?id='.$id.'&type='.$type).'&backtolist='.urlencode($_SERVER["PHP_SELF"].'?id='.$id.'&type='.$type).'">'.img_delete().'</a>';
		$entry .= '</td>';

		$entry .= '</tr>';
		$entry .= '</table>';

		$data[] = array('rowid' => $val['rowid'], 'fk_menu' => $val['fk_parent'], 'entry' => $entry);
	}

	$nbofentries = (count($data) - 1);
	if ($nbofentries > 0) {
		require_once DOL_DOCUMENT_ROOT.'/core/lib/treeview.lib.php';
		print '<tr class="pair">';
		print '<td colspan="3">';

		// $data[0] is the current shown category, to don'T show the current category use $data[1] instead
		tree_recur($data, $data[1], 0);

		print '</td>';
		print '</tr>';
	} else {
		print '<tr class="pair">';
		print '<td colspan="3">';
		print '<table class="nobordernopadding">';

		print '<tr class="nobordernopadding">';
		print '<td>'.img_picto_common('', 'treemenu/branchbottom.gif').'</td>';
		print '<td valign="middle">'.$langs->trans("NoCategoryYet").'</td>';
		print '<td>&nbsp;</td>';
		print '</tr>';

		print '</table>';
		print '</td>';
		print '</tr>';
	}
}

print "</table>";
print "</div>";

// List of mass actions available
$arrayofmassactions = array(
	//'validate'=>$langs->trans("Validate"),
	//'generate_doc'=>$langs->trans("ReGeneratePDF"),
	//'builddoc'=>$langs->trans("PDFMerge"),
	//'presend'=>$langs->trans("SendByMail"),
);
$massactionbutton = $form->selectMassAction('', $arrayofmassactions);

$typeid = $type;


// List of products or services (type is type of category)
if ($type == Categorie::TYPE_PRODUCT) {
	$permission = ($user->rights->produit->creer || $user->rights->service->creer);

	$prods = $object->getObjectsInCateg($type, 0, $limit, $offset);
	if ($prods < 0) {
		dol_print_error($db, $object->error, $object->errors);
	} else {
		// Form to add record into a category
		$showclassifyform = 1;
		if ($showclassifyform) {
			print '<br>';
			print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="typeid" value="'.$typeid.'">';
			print '<input type="hidden" name="type" value="'.$typeid.'">';
			print '<input type="hidden" name="id" value="'.$object->id.'">';
			print '<input type="hidden" name="action" value="addintocategory">';
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre"><td>';
			print $langs->trans("AddProductServiceIntoCategory").' &nbsp;';
			$form->select_produits('', 'elemid', '', 0, 0, -1, 2, '', 1);
			print '<input type="submit" class="button buttongen" value="'.$langs->trans("ClassifyInCategory").'"></td>';
			print '</tr>';
			print '</table>';
			print '</form>';
		}

		print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="typeid" value="'.$typeid.'">';
		print '<input type="hidden" name="type" value="'.$typeid.'">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print '<input type="hidden" name="action" value="list">';

		print '<br>';
		$param = '&limit='.$limit.'&id='.$id.'&type='.$type; $num = count($prods); $nbtotalofrecords = ''; $newcardbutton = '';
		print_barre_liste($langs->trans("ProductsAndServices"), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'products', 0, $newcardbutton, '', $limit);


		print '<table class="noborder centpercent">'."\n";
		print '<tr class="liste_titre"><td colspan="3">'.$langs->trans("Ref").'</td></tr>'."\n";

		if (count($prods) > 0) {
			$i = 0;
			foreach ($prods as $prod) {
				$i++;
				if ($i > $limit) {
					break;
				}

				print "\t".'<tr class="oddeven">'."\n";
				print '<td class="nowrap" valign="top">';
				print $prod->getNomUrl(1);
				print "</td>\n";
				print '<td class="tdtop">'.$prod->label."</td>\n";
				// Link to delete from category
				print '<td class="right">';
				if ($permission) {
					print "<a href= '".$_SERVER['PHP_SELF']."?".(empty($socid) ? 'id' : 'socid')."=".$object->id."&amp;type=".$typeid."&amp;removeelem=".$prod->id."'>";
					print $langs->trans("DeleteFromCat");
					print img_picto($langs->trans("DeleteFromCat"), 'unlink', '', false, 0, 0, '', 'paddingleft');
					print "</a>";
				}
				print '</td>';
				print "</tr>\n";
			}
		} else {
			print '<tr class="oddeven"><td colspan="2" class="opacitymedium">'.$langs->trans("ThisCategoryHasNoItems").'</td></tr>';
		}
		print "</table>\n";

		print '</form>'."\n";
	}
}

if ($type == Categorie::TYPE_CUSTOMER) {
	$permission = $user->rights->societe->creer;

	$socs = $object->getObjectsInCateg($type, 0, $limit, $offset);
	if ($socs < 0) {
		dol_print_error($db, $object->error, $object->errors);
	} else {
		// Form to add record into a category
		$showclassifyform = 1;
		if ($showclassifyform) {
			print '<br>';
			print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="typeid" value="'.$typeid.'">';
			print '<input type="hidden" name="type" value="'.$typeid.'">';
			print '<input type="hidden" name="id" value="'.$object->id.'">';
			print '<input type="hidden" name="action" value="addintocategory">';
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre"><td>';
			print $langs->trans("AddCustomerIntoCategory").' &nbsp;';
			print $form->select_company('', 'elemid', 's.client IN (1,3)');
			print '<input type="submit" class="button buttongen" value="'.$langs->trans("ClassifyInCategory").'"></td>';
			print '</tr>';
			print '</table>';
			print '</form>';
		}

		print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="typeid" value="'.$typeid.'">';
		print '<input type="hidden" name="type" value="'.$typeid.'">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print '<input type="hidden" name="action" value="list">';

		print '<br>';
		$param = '&limit='.$limit.'&id='.$id.'&type='.$type; $num = count($socs); $nbtotalofrecords = ''; $newcardbutton = '';
		print_barre_liste($langs->trans("Customers"), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'companies', 0, $newcardbutton, '', $limit);

		print '<table class="noborder centpercent">'."\n";
		print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("Name").'</td></tr>'."\n";

		if (count($socs) > 0) {
			$i = 0;
			foreach ($socs as $key => $soc) {
				$i++;
				if ($i > $limit) {
					break;
				}

				print "\t".'<tr class="oddeven">'."\n";
				print '<td class="nowrap" valign="top">';
				print $soc->getNomUrl(1);
				print "</td>\n";
				// Link to delete from category
				print '<td class="right">';
				if ($permission) {
					print "<a href= '".$_SERVER['PHP_SELF']."?".(empty($socid) ? 'id' : 'socid')."=".$object->id."&amp;type=".$typeid."&amp;removeelem=".$soc->id."'>";
					print $langs->trans("DeleteFromCat");
					print img_picto($langs->trans("DeleteFromCat"), 'unlink', '', false, 0, 0, '', 'paddingleft');
					print "</a>";
				}
				print '</td>';
				print "</tr>\n";
			}
		} else {
			print '<tr class="oddeven"><td colspan="2" class="opacitymedium">'.$langs->trans("ThisCategoryHasNoItems").'</td></tr>';
		}
		print "</table>\n";

		print '</form>'."\n";
	}
}


if ($type == Categorie::TYPE_SUPPLIER) {
	$permission = $user->rights->societe->creer;

	$socs = $object->getObjectsInCateg($type, 0, $limit, $offset);
	if ($socs < 0) {
		dol_print_error($db, $object->error, $object->errors);
	} else {
		// Form to add record into a category
		$showclassifyform = 1;
		if ($showclassifyform) {
			print '<br>';
			print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
			print '<input type="hidden" name="token" value="'.newToken().'">';
			print '<input type="hidden" name="typeid" value="'.$typeid.'">';
			print '<input type="hidden" name="type" value="'.$typeid.'">';
			print '<input type="hidden" name="id" value="'.$object->id.'">';
			print '<input type="hidden" name="action" value="addintocategory">';
			print '<table class="noborder centpercent">';
			print '<tr class="liste_titre"><td>';
			print $langs->trans("AddSupplierIntoCategory").' &nbsp;';
			print $form->select_company('', 'elemid', 's.fournisseur = 1');
			print '<input type="submit" class="button buttongen" value="'.$langs->trans("ClassifyInCategory").'"></td>';
			print '</tr>';
			print '</table>';
			print '</form>';
		}

		print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="typeid" value="'.$typeid.'">';
		print '<input type="hidden" name="type" value="'.$typeid.'">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print '<input type="hidden" name="action" value="list">';

		print '<br>';
		$param = '&limit='.$limit.'&id='.$id.'&type='.$type; $num = count($socs); $nbtotalofrecords = ''; $newcardbutton = '';
		print_barre_liste($langs->trans("Suppliers"), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'companies', 0, $newcardbutton, '', $limit);

		print '<table class="noborder centpercent">'."\n";
		print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("Name")."</td></tr>\n";

		if (count($socs) > 0) {
			$i = 0;
			foreach ($socs as $soc) {
				$i++;
				if ($i > $limit) {
					break;
				}

				print "\t".'<tr class="oddeven">'."\n";
				print '<td class="nowrap" valign="top">';
				print $soc->getNomUrl(1);
				print "</td>\n";
				// Link to delete from category
				print '<td class="right">';
				if ($permission) {
					print "<a href= '".$_SERVER['PHP_SELF']."?".(empty($socid) ? 'id' : 'socid')."=".$object->id."&amp;type=".$typeid."&amp;removeelem=".$soc->id."'>";
					print $langs->trans("DeleteFromCat");
					print img_picto($langs->trans("DeleteFromCat"), 'unlink', '', false, 0, 0, '', 'paddingleft');
					print "</a>";
				}
				print '</td>';

				print "</tr>\n";
			}
		} else {
			print '<tr class="oddeven"><td colspan="2" class="opacitymedium">'.$langs->trans("ThisCategoryHasNoItems").'</td></tr>';
		}
		print "</table>\n";

		print '</form>'."\n";
	}
}

// List of members
if ($type == Categorie::TYPE_MEMBER) {
	require_once DOL_DOCUMENT_ROOT.'/adherents/class/adherent.class.php';

	$permission = $user->rights->adherent->creer;

	$prods = $object->getObjectsInCateg($type, 0, $limit, $offset);
	if ($prods < 0) {
		dol_print_error($db, $object->error, $object->errors);
	} else {
		print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="typeid" value="'.$typeid.'">';
		print '<input type="hidden" name="type" value="'.$typeid.'">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print '<input type="hidden" name="action" value="list">';

		print '<br>';
		$param = '&limit='.$limit.'&id='.$id.'&type='.$type; $num = count($prods); $nbtotalofrecords = ''; $newcardbutton = '';
		print_barre_liste($langs->trans("Member"), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'members', 0, $newcardbutton, '', $limit);

		print "<table class='noborder' width='100%'>\n";
		print '<tr class="liste_titre"><td colspan="4">'.$langs->trans("Name").'</td></tr>'."\n";

		if (count($prods) > 0) {
			$i = 0;
			foreach ($prods as $key => $member) {
				$i++;
				if ($i > $limit) {
					break;
				}

				print "\t".'<tr class="oddeven">'."\n";
				print '<td class="nowrap" valign="top">';
				$member->ref = $member->login;
				print $member->getNomUrl(1, 0);
				print "</td>\n";
				print '<td class="tdtop">'.$member->lastname."</td>\n";
				print '<td class="tdtop">'.$member->firstname."</td>\n";
				// Link to delete from category
				print '<td class="right">';
				if ($permission) {
					print "<a href= '".$_SERVER['PHP_SELF']."?".(empty($socid) ? 'id' : 'socid')."=".$object->id."&amp;type=".$typeid."&amp;removeelem=".$member->id."'>";
					print $langs->trans("DeleteFromCat");
					print img_picto($langs->trans("DeleteFromCat"), 'unlink', '', false, 0, 0, '', 'paddingleft');
					print "</a>";
				}
				print "</tr>\n";
			}
		} else {
			print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans("ThisCategoryHasNoItems").'</td></tr>';
		}
		print "</table>\n";

		print '</form>'."\n";
	}
}

// Categorie contact
if ($type == Categorie::TYPE_CONTACT) {
	$permission = $user->rights->societe->creer;

	$contacts = $object->getObjectsInCateg($type, 0, $limit, $offset);
	if ($contacts < 0) {
		dol_print_error($db, $object->error, $object->errors);
	} else {
		print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="typeid" value="'.$typeid.'">';
		print '<input type="hidden" name="type" value="'.$typeid.'">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print '<input type="hidden" name="action" value="list">';

		print '<br>';
		$param = '&limit='.$limit.'&id='.$id.'&type='.$type;
		$num = count($contacts);
		$nbtotalofrecords = '';
		$newcardbutton = '';
		$objsoc = new Societe($db);
		print_barre_liste($langs->trans("Contact"), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'contact', 0, $newcardbutton, '', $limit);

		print '<table class="noborder centpercent">'."\n";
		print '<tr class="liste_titre"><td colspan="2">'.$langs->trans("Ref").'</td></tr>'."\n";

		if (count($contacts) > 0) {
			$i = 0;
			foreach ($contacts as $key => $contact) {
				$i++;
				if ($i > $limit) {
					break;
				}

				print "\t".'<tr class="oddeven">'."\n";
				print '<td class="nowrap" valign="top">';
				print $contact->getNomUrl(1, 'category');
				if ($contact->socid > 0) {
					$objsoc->fetch($contact->socid);
					print ' - ';
					print $objsoc->getNomUrl(1, 'contact');
				}
				print "</td>\n";
				// Link to delete from category
				print '<td class="right">';
				if ($permission) {
					print "<a href= '".$_SERVER['PHP_SELF']."?".(empty($socid) ? 'id' : 'socid')."=".$object->id."&amp;type=".$typeid."&amp;removeelem=".$contact->id."'>";
					print $langs->trans("DeleteFromCat");
					print img_picto($langs->trans("DeleteFromCat"), 'unlink', '', false, 0, 0, '', 'paddingleft');
					print "</a>";
				}
				print '</td>';
				print "</tr>\n";
			}
		} else {
			print '<tr class="oddeven"><td colspan="2" class="opacitymedium">'.$langs->trans("ThisCategoryHasNoItems").'</td></tr>';
		}
		print "</table>\n";

		print '</form>'."\n";
	}
}

// List of bank accounts
if ($type == Categorie::TYPE_ACCOUNT) {
	require_once DOL_DOCUMENT_ROOT.'/compta/bank/class/account.class.php';

	$permission = $user->rights->banque->creer;

	$accounts = $object->getObjectsInCateg($type, 0, $limit, $offset);
	if ($accounts < 0) {
		dol_print_error($db, $object->error, $object->errors);
	} else {
		print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="typeid" value="'.$typeid.'">';
		print '<input type="hidden" name="type" value="'.$typeid.'">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print '<input type="hidden" name="action" value="list">';

		print '<br>';
		$param = '&limit='.$limit.'&id='.$id.'&type='.$type; $num = count($accounts); $nbtotalofrecords = ''; $newcardbutton = '';
		print_barre_liste($langs->trans("Account"), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'bank_account', 0, $newcardbutton, '', $limit);

		print "<table class='noborder' width='100%'>\n";
		print '<tr class="liste_titre"><td colspan="4">'.$langs->trans("Ref").'</td></tr>'."\n";

		if (count($accounts) > 0) {
			$i = 0;
			foreach ($accounts as $key => $account) {
				$i++;
				if ($i > $limit) {
					break;
				}

				print "\t".'<tr class="oddeven">'."\n";
				print '<td class="nowrap" valign="top">';
				print $account->getNomUrl(1, 0);
				print "</td>\n";
				print '<td class="tdtop">'.$account->bank."</td>\n";
				print '<td class="tdtop">'.$account->number."</td>\n";
				// Link to delete from category
				print '<td class="right">';
				if ($permission) {
					print "<a href= '".$_SERVER['PHP_SELF']."?".(empty($socid) ? 'id' : 'socid')."=".$object->id."&amp;type=".$typeid."&amp;removeelem=".$account->id."'>";
					print $langs->trans("DeleteFromCat");
					print img_picto($langs->trans("DeleteFromCat"), 'unlink', '', false, 0, 0, '', 'paddingleft');
					print "</a>";
				}
				print "</tr>\n";
			}
		} else {
			print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans("ThisCategoryHasNoItems").'</td></tr>';
		}
		print "</table>\n";

		print '</form>'."\n";
	}
}

// List of Project
if ($type == Categorie::TYPE_PROJECT) {
	require_once DOL_DOCUMENT_ROOT.'/projet/class/project.class.php';

	$permission = $user->rights->projet->creer;

	$objects = $object->getObjectsInCateg($type, 0, $limit, $offset);
	if ($objects < 0) {
		dol_print_error($db, $object->error, $object->errors);
	} else {
		print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="typeid" value="'.$typeid.'">';
		print '<input type="hidden" name="type" value="'.$typeid.'">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print '<input type="hidden" name="action" value="list">';

		print '<br>';
		$param = '&limit='.$limit.'&id='.$id.'&type='.$type; $num = count($objects); $nbtotalofrecords = ''; $newcardbutton = '';

		print_barre_liste($langs->trans("Project"), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'project', 0, $newcardbutton, '', $limit);

		print "<table class='noborder' width='100%'>\n";
		print '<tr class="liste_titre"><td colspan="4">'.$langs->trans("Ref").'</td></tr>'."\n";

		if (count($objects) > 0) {
			$i = 0;
			foreach ($objects as $key => $project) {
				$i++;
				if ($i > $limit) {
					break;
				}

				print "\t".'<tr class="oddeven">'."\n";
				print '<td class="nowrap" valign="top">';
				print $project->getNomUrl(1);
				print "</td>\n";
				print '<td class="tdtop">'.$project->ref."</td>\n";
				print '<td class="tdtop">'.$project->title."</td>\n";
				// Link to delete from category
				print '<td class="right">';
				if ($permission) {
					print "<a href= '".$_SERVER['PHP_SELF']."?".(empty($socid) ? 'id' : 'socid')."=".$object->id."&amp;type=".$typeid."&amp;removeelem=".$project->id."'>";
					print $langs->trans("DeleteFromCat");
					print img_picto($langs->trans("DeleteFromCat"), 'unlink', '', false, 0, 0, '', 'paddingleft');
					print "</a>";
				}
				print "</tr>\n";
			}
		} else {
			print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans("ThisCategoryHasNoItems").'</td></tr>';
		}
		print "</table>\n";

		print '</form>'."\n";
	}
}

// List of users
if ($type == Categorie::TYPE_USER) {
	require_once DOL_DOCUMENT_ROOT.'/user/class/user.class.php';

	$users = $object->getObjectsInCateg($type);
	if ($users < 0) {
		dol_print_error($db, $object->error, $object->errors);
	} else {
		print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="typeid" value="'.$typeid.'">';
		print '<input type="hidden" name="type" value="'.$typeid.'">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print '<input type="hidden" name="action" value="list">';

		print '<br>';

		$param = '&limit='.$limit.'&id='.$id.'&type='.$type;
		$num = count($users);

		print_barre_liste($langs->trans("Users"), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, '', 'user', 0, '', '', $limit);

		print "<table class='noborder' width='100%'>\n";
		print '<tr class="liste_titre"><td colspan="4">'.$langs->trans("Users").' <span class="badge">'.$num.'</span></td></tr>'."\n";

		if (count($users) > 0) {
			// Use "$userentry" here, because "$user" is the current user
			foreach ($users as $key => $userentry) {
				print "\t".'<tr class="oddeven">'."\n";
				print '<td class="nowrap" valign="top">';
				print $userentry->getNomUrl(1);
				print "</td>\n";
				print '<td class="tdtop">'.$userentry->job."</td>\n";

				// Link to delete from category
				print '<td class="right">';
				if ($user->rights->user->user->creer) {
					print "<a href= '".$_SERVER['PHP_SELF']."?".(empty($socid) ? 'id' : 'socid')."=".$object->id."&amp;type=".$type."&amp;removeelem=".$userentry->id."'>";
					print $langs->trans("DeleteFromCat");
					print img_picto($langs->trans("DeleteFromCat"), 'unlink', '', false, 0, 0, '', 'paddingleft');
					print "</a>";
				}
				print "</tr>\n";
			}
		} else {
			print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans("ThisCategoryHasNoItems").'</td></tr>';
		}
		print "</table>\n";

		print '</form>'."\n";
	}
}


// List of warehouses
if ($type == Categorie::TYPE_WAREHOUSE) {
	$permission = $user->rights->stock->creer;

	require_once DOL_DOCUMENT_ROOT.'/product/stock/class/entrepot.class.php';

	$objects = $object->getObjectsInCateg($type, 0, $limit, $offset);
	if ($objects < 0) {
		dol_print_error($db, $object->error, $object->errors);
	} else {
		print '<form method="post" action="'.$_SERVER["PHP_SELF"].'">';
		print '<input type="hidden" name="token" value="'.newToken().'">';
		print '<input type="hidden" name="typeid" value="'.$typeid.'">';
		print '<input type="hidden" name="type" value="'.$typeid.'">';
		print '<input type="hidden" name="id" value="'.$object->id.'">';
		print '<input type="hidden" name="action" value="list">';

		print '<br>';
		$param = '&limit='.$limit.'&id='.$id.'&type='.$type; $num = count($objects); $nbtotalofrecords = ''; $newcardbutton = '';

		print_barre_liste($langs->trans("Warehouses"), $page, $_SERVER["PHP_SELF"], $param, $sortfield, $sortorder, $massactionbutton, $num, $nbtotalofrecords, 'stock', 0, $newcardbutton, '', $limit);

		print "<table class='noborder' width='100%'>\n";
		print '<tr class="liste_titre"><td colspan="4">'.$langs->trans("Ref").'</td></tr>'."\n";

		if (count($objects) > 0) {
			$i = 0;
			foreach ($objects as $key => $project) {
				$i++;
				if ($i > $limit) {
					break;
				}

				print "\t".'<tr class="oddeven">'."\n";
				print '<td class="nowrap" valign="top">';
				print $project->getNomUrl(1);
				print "</td>\n";
				print '<td class="tdtop">'.$project->ref."</td>\n";
				print '<td class="tdtop">'.$project->title."</td>\n";
				// Link to delete from category
				print '<td class="right">';
				if ($permission) {
					print "<a href= '".$_SERVER['PHP_SELF']."?".(empty($socid) ? 'id' : 'socid')."=".$object->id."&amp;type=".$typeid."&amp;removeelem=".$project->id."'>";
					print $langs->trans("DeleteFromCat");
					print img_picto($langs->trans("DeleteFromCat"), 'unlink', '', false, 0, 0, '', 'paddingleft');
					print "</a>";
				}
				print "</tr>\n";
			}
		} else {
			print '<tr class="oddeven"><td colspan="3" class="opacitymedium">'.$langs->trans("ThisCategoryHasNoItems").'</td></tr>';
		}
		print "</table>\n";

		print '</form>'."\n";
	}
}


// End of page
llxFooter();
$db->close();
