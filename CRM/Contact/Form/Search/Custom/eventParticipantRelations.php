<?php

/*
 +--------------------------------------------------------------------+
 | CiviCRM version 3.3                                                |
 +--------------------------------------------------------------------+
 | Copyright CiviCRM LLC (c) 2004-2010                                |
 +--------------------------------------------------------------------+
 | This file is a part of CiviCRM.                                    |
 |                                                                    |
 | CiviCRM is free software; you can copy, modify, and distribute it  |
 | under the terms of the GNU Affero General Public License           |
 | Version 3, 19 November 2007 and the CiviCRM Licensing Exception.   |
 |                                                                    |
 | CiviCRM is distributed in the hope that it will be useful, but     |
 | WITHOUT ANY WARRANTY; without even the implied warranty of         |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.               |
 | See the GNU Affero General Public License for more details.        |
 |                                                                    |
 | You should have received a copy of the GNU Affero General Public   |
 | License and the CiviCRM Licensing Exception along                  |
 | with this program; if not, contact CiviCRM LLC                     |
 | at info[AT]civicrm[DOT]org. If you have questions about the        |
 | GNU Affero General Public License or the licensing of CiviCRM,     |
 | see the CiviCRM license FAQ at http://civicrm.org/licensing        |
 +--------------------------------------------------------------------+
*/

/**
 *
 * @package CRM
 * @copyright CiviCRM LLC (c) 2004-2010
 * $Id$
 *
 */

require_once 'CRM/Contact/Form/Search/Custom/Base.php';

class CRM_Contact_Form_Search_Custom_eventParticipantRelations
   extends    CRM_Contact_Form_Search_Custom_Base
   implements CRM_Contact_Form_Search_Interface {

    protected $_debug = 0;

    function __construct( &$formValues ) {
        parent::__construct( $formValues );

        $this->_columns = array( ts('Contact Id')   => 'contact_id'  ,
                                 ts('Contact Type') => 'contact_type',
                                 ts('Name')         => 'sort_name' );
    }

        function buildForm( &$form ) {
        /**
         * You can define a custom title for the search form
         */
        $this->setTitle('Find Relatives of Event Participants');

        /**
         * Define the search form fields here
         */

        $dates = array( 'any' => 'Any', 'this_year' => 'This Year', 'last_year' => 'Last Year', 'next_summer' => 'Next Summer' );
        $now = date('Y');
        $year = 2005;
        while ($year <= $now) {
          $dates[$year] = $year;
          ++$year;
        }
        $form->add('select', 'dates',  ts( 'Year' ), $dates );

        $sql = "SELECT value, label FROM civicrm_option_value WHERE is_active <> 0 AND option_group_id = (SELECT id FROM civicrm_option_group WHERE name = 'event_type') ORDER BY label";
        $dao =& CRM_Core_DAO::executeQuery($sql);
        while ($dao->fetch()) {
            $form->addElement('checkbox', "event_type_id[{$dao->value}]", 'Event Type', $dao->label);
        }

        $sql = "SELECT id, label FROM civicrm_participant_status_type WHERE is_active <> 0 ORDER BY weight";
        $dao =& CRM_Core_DAO::executeQuery($sql);
        while ($dao->fetch()) {
          $form->addElement('checkbox', "status_type_id[{$dao->id}]", 'Status Type', $dao->label);
        }

        $sql = "SELECT id, label_a_b, label_b_a FROM civicrm_relationship_type WHERE contact_type_a = 'Individual' AND  contact_type_b = 'Individual' AND is_active <> 0";
        $dao =& CRM_Core_DAO::executeQuery($sql);
        while ($dao->fetch()) {
          if ($dao->label_a_b != $dao->label_b_a) {
            $form->addElement('checkbox', "relationship_type_id[{$dao->id}.a_b]", 'Relationship Type', $dao->label_a_b);
            $form->addElement('checkbox', "relationship_type_id[{$dao->id}.b_a]", 'Relationship Type', $dao->label_b_a);
          }
          else {
            $form->addElement('checkbox', "relationship_type_id[{$dao->id}.both]", 'Relationship Type', $dao->label_a_b);
          }
        }
//         $form->addRule('relationship_type_id', ts('Check at least one box'), 'required');

        /**
         * If you are using the sample template, this array tells the template fields to render
         * for the search form.
         */
        $form->assign( 'elements', array('dates', 'event_type_id', 'status_type_id', 'relationship_type_id') );
    }

    /**
     * Define the smarty template used to layout the search form and results listings.
     */
    function templateFile( ) {
        return 'CRM/Contact/Form/Search/Custom/eventParticipantRelations.tpl';
    }

    function summary( ) {
        return null;
    }

    function all($offset = 0, $rowcount = 0, $sort = NULL, $includeContactIDs = FALSE, $justIDs = FALSE) {

        $selectClause = "contact_a.id  as contact_id,
                         contact_a.contact_type as contact_type,
                         contact_a.sort_name as sort_name";

        $groupBy = " GROUP BY contact_id ";
        return $this->sql( $selectClause,
                           $offset, $rowcount, $sort,
                           $includeContactIDs, $groupBy );

    }
    
    function from( ) {
        if ( ! empty($this->_formValues['event_type_id'] ) ) {
          $event_clause = 'AND civicrm_event.event_type_id IN ('. implode(',', array_keys($this->_formValues['event_type_id'])) .')';
        }
        else {
          $event_clause = '';
        }
        if ( ! empty($this->_formValues['status_type_id'] ) ) {
          $status_clause = 'AND civicrm_participant.status_id IN ('. implode(',', array_keys($this->_formValues['status_type_id'])) .')';
        }
        else {
          $status_clause = '';
        }
        $date = $this->_formValues['dates'];
        if (is_numeric($date)) {
          $year = $date;
        }
        else {
          switch ($date) {
            case 'last_year':
              $year = date('Y') - 1;
            break;
            case 'next_summer':
              $year = woolman_camp_next_year();
            break;
            default:
              $year = date('Y');
          }
        }
        if ($date != 'any') {
          $date_clause = 'AND YEAR(civicrm_event.start_date) = ' . $year;
        }
        else {
          $date_clause = '';
        }
        
        //define table name
        $randomNum = md5( uniqid( ) );
        $this->_tableName = "civicrm_temp_ev_par_rel_{$randomNum}";

        //grab the contacts added in the date range first
        $sql = "CREATE TEMPORARY TABLE {$this->_tableName} ( id int primary key ) ENGINE=HEAP";

        CRM_Core_DAO::executeQuery( $sql );

        $tempInfo =
         "INSERT INTO {$this->_tableName} ( id )
          SELECT DISTINCT civicrm_participant.contact_id AS id FROM civicrm_participant INNER JOIN civicrm_event ON civicrm_participant.event_id = civicrm_event.id WHERE civicrm_participant.is_test = 0 $event_clause $date_clause $status_clause";

        CRM_Core_DAO::executeQuery( $tempInfo, CRM_Core_DAO::$_nullArray );

        $from = "FROM civicrm_contact contact_a";

        //this makes smart groups using this search compatible w/ CiviMail
        $from .= " LEFT JOIN civicrm_email ON (contact_a.id = civicrm_email.contact_id)";

        return $from;
    }

    function where( $includeContactIDs = false ) {
         $clauses = '(';
         if ( !empty( $this->_formValues['relationship_type_id'] ) ) {
            foreach ($this->_formValues['relationship_type_id'] as $type => $label) {
              list($rid, $rtype) = explode('.', $type);
              if ($rtype == 'b_a' || $rtype == 'both') {
              if ($clauses != '(') {
                $clauses .= ' OR ';
              }
              $clauses .= "contact_a.id IN (SELECT contact_id_b FROM civicrm_relationship WHERE relationship_type_id = $rid AND contact_id_a IN (SELECT id FROM {$this->_tableName} ))";
            }
            if ($rtype == 'a_b' || $rtype == 'both') {
              if ($clauses != '(') {
                $clauses .= ' OR ';
              }
              $clauses .= "contact_a.id IN (SELECT contact_id_a FROM civicrm_relationship WHERE relationship_type_id = $rid AND contact_id_b IN (SELECT id FROM {$this->_tableName} ))";
            }
          }
        }
        $clauses .= ')';
        
        if ( ! empty( $contactIDs ) ) {
            $contactIDs = implode( ', ', $contactIDs );
            $clauses .= " AND contact_a.id IN ( $contactIDs )";
        }
            
        return $clauses;
    }

    function setTitle( $title ) {
        if ( $title ) {
            CRM_Utils_System::setTitle( $title );
        } else {
            CRM_Utils_System::setTitle(ts('Search'));
        }
    }
    
    function count( ) {
        $sql = $this->all( );
           
        $dao = CRM_Core_DAO::executeQuery( $sql,
                                             CRM_Core_DAO::$_nullArray );
        return $dao->N;
    }
    
    function __destruct( ) {
        //drop the temp. tables if they exist
      if ($this->_tableName) {
        $sql = "DROP TEMPORARY TABLE IF EXISTS {$this->_tableName}";
        CRM_Core_DAO::executeQuery( $sql, CRM_Core_DAO::$_nullArray ) ;
      }
    }
}


