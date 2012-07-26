<?php

require_once 'CRM/Contact/Form/Search/Custom/Base.php';

class CRM_Contact_Form_Search_Custom_relationsToWoolman
   extends    CRM_Contact_Form_Search_Custom_Base
   implements CRM_Contact_Form_Search_Interface {

    function __construct( &$formValues ) {
        parent::__construct( $formValues );

        $this->_columns = array( ts('Contact Id')   => 'contact_id'  ,
                                 ts('Name')         => 'sort_name',
                                );
    }

    function buildForm( &$form ) {
        $woolman = array(
          'current_students' => 'Current Students',
          'current_parents' => 'Parents of Current Students',
          'current_family' => 'Family of Current Students',
          'jws_alum' => 'JWS Alum',
          'ws_alum' => 'WS Alum',
          'jws_parents' => 'Parents of JWS Alum',
          'ws_parents' => 'Parents of WS Alum',
          'jws_family' => 'Family of JWS Alum',
          'ws_family' => 'Family of WS Alum',
        );
        $form->addElement('select', 'woolman', 'Relationship To Woolman:', $woolman);
        $elements = array('woolman');

        $status = woolman_get_civi_options(114);
        foreach ($status as $key => $label) {
          $form->addElement('checkbox', "status_$key", "Exclude $label Students");
          $elements[] = "status_$key";
        }

        $this->setTitle('Woolman Students');

         /**
         * if you are using the standard template, this array tells the template what elements
         * are part of the search criteria
         */
        $form->assign( 'elements', $elements );
    }

    function all( $offset = 0, $rowcount = 0, $sort = null,
                  $includeContactIDs = false ) {
        $selectClause = "
contact_a.id           as contact_id,
contact_a.sort_name    as sort_name
";
        return $this->sql( $selectClause,
                           $offset, $rowcount, $sort,
                           $includeContactIDs, null );

    }

    function from( ) {
        return "FROM civicrm_contact contact_a";
    }

    function where( $includeContactIDs = false ) {
        $params = $exclude = array( );
        $where = 'SELECT contact_id_a FROM civicrm_relationship WHERE contact_id_b = 243 AND relationship_type_id = 10 AND case_id IS NULL';

        list( $program, $people ) = split('_', $this->_formValues['woolman']);

        if ($program == 'current') {
          $where .= ' AND start_date < DATE_ADD(CURDATE(), interval 1 month) AND end_date >= DATE_SUB(CURDATE(), interval 1 week)';
        }
        elseif ($program == 'ws') {
          $where .= ' AND YEAR(start_date) > 2003 AND end_date <= CURDATE()';
        }
        elseif ($program == 'jws') {
          $where .= ' AND (YEAR(start_date) < 2003 OR YEAR(end_date) < 2003 OR (start_date IS NULL AND end_date IS NULL AND is_active = 0))';
        }

        foreach (woolman_get_civi_options(114) as $key => $label) {
          if (!empty($this->_formValues["status_$key"])) {
            $exclude[] = $key;
          }
        }
        if ($exclude) {
          $where .= " AND id NOT IN (SELECT entity_id FROM civicrm_value_student_info_14 WHERE reason_for_leaving_52 IN ('" . implode("','", $exclude) . "'))";
        }

        if ($people == 'parents') {
          $where = "SELECT contact_id_b FROM civicrm_relationship WHERE relationship_type_id = 1 AND is_active = 1 AND contact_id_a IN (\n$where)";
        }
        elseif ($people == 'family') {
          $where = "SELECT contact_id_b AS cid FROM civicrm_relationship WHERE relationship_type_id IN (1,2,3,16) AND is_active = 1 AND contact_id_a IN (\n$where)\nUNION SELECT contact_id_a AS cid FROM civicrm_relationship WHERE relationship_type_id IN (1,2,3,16) AND is_active = 1 AND contact_id_b IN (\n$where)";
        }

        $where  = "contact_a.contact_type = 'Individual' AND contact_a.is_deleted = 0 AND contact_a.id IN (\n$where)";
        return $this->whereClause( $where, $params );
    }

    function templateFile( ) {
        return 'CRM/Contact/Form/Search/Custom.tpl';
    }

    function setTitle( $title ) {
        if ( $title ) {
            CRM_Utils_System::setTitle( $title );
        } else {
            CRM_Utils_System::setTitle('Search');
        }
    }
}
