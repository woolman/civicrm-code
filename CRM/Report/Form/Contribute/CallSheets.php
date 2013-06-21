<?php

require_once 'CRM/Report/Form.php';
class CRM_Report_Form_Contribute_CallSheets extends CRM_Report_Form {

    protected $_addressField   = false;
    protected $_emailField     = false;
    protected $_phoneField     = false;
    protected $_activityField  = false;
    protected $_relationField  = false;
    protected $_summary        = null;
    protected $_customGroupGroupBy = false;
    protected $_customGroupExtends = array( 'Contact', 'Address' );

    function __construct( ) {

          $this->_columns =
            array( 'civicrm_contact' =>
                   array( 'dao'     => 'CRM_Contact_DAO_Contact',
                          'fields'  =>
                          array( 'sort_name' =>
                                 array( 'title'      => ts( 'Sort Name' ),
                                        'required'   => true,
                                        'default'    => true,
                                        'no_repeat'  => true ),
                                 'display_name' =>
                                 array( 'title'      => ts( 'Display Name' ),
                                        'default'    => true,
                                        'no_repeat'  => true ),
                                 'addressee_display' =>
                                 array( 'title'      => ts( 'Addressee' ),
                                        'no_repeat'  => true ),
                                 'postal_greeting_display' =>
                                 array( 'title'      => ts( 'Postal Greeting' ),
                                        'no_repeat'  => true ),
                                 'id'           =>
                                 array( 'no_display' => true,
                                        'required'   => true ),),

                          'filters'  =>
                          array('sort_name'     =>
                                array( 'title'    => ts( 'Contact Name' ),
                                       'operator' => 'like' ),
                                'id' =>
                                array( 'no_display'  => true ), ),

                          'grouping'=> 'contact-fields',
                          ),



                   'civicrm_contribution' =>
                     array( 'dao'     => 'CRM_Contribute_DAO_Contribution',
                            'fields'  =>
                            array(
                               'first_contribution_date' => array('title'   => ts( 'First Contribution Date' ),
                                                                  'default' => true ),
                               'most_recent'             => array('title'   => ts( 'Last Contribution' ),
                                                                  'default' => true ),
                               'number_of'               => array('title'   => ts( 'Number of Contributions' ),                                   'default' => true ),
                               'total_amount'            => array('title'   => ts( 'Average Contribution Amount' ),
                                                                  'default' => true ),
                                  ),

                          'grouping'=> 'contri-fields',
                        ),

                   'civicrm_address' =>
                     array( 'dao'      => 'CRM_Core_DAO_Address',
                            'fields'   => array('street_address' => array('title' => 'Mailing Address')),
                            'grouping' => 'contact-fields',
                            ),

                   'civicrm_email' =>
                     array( 'dao'    => 'CRM_Core_DAO_Email',
                            'fields' =>
                            array( 'email' => null),
                            'grouping'=> 'contact-fields',
                            ),

                   'civicrm_phone' =>
                    array( 'dao'    => 'CRM_Core_DAO_Phone',
                           'fields' =>
                           array( 'phone' => null),
                           'grouping'=> 'contact-fields',
                           ),

                   'civicrm_activity' =>
                    array( 'dao'    => 'CRM_Activity_DAO_Activity',
                           'fields' =>
                             array( 'activity' => array('title' => ts( 'Last Call/Visit' ) ),
                                    'details'  => array('title' => ts( 'Note From Call/Visit' ) )
                                  ),
                           'grouping'=> 'contri-fields',
                         ),

                   );
        $this->_groupFilter = true;
        $this->_tagFilter = true;
        parent::__construct( );
    }

    function preProcess( ) {
      $this->assign( 'reportTitle', ts('Woolman Donor Info') );
      parent::preProcess( );
    }

    function select( ) {
        $select = $this->_columnHeaders = array( );

        foreach ( $this->_columns as $tableName => $table ) {
            if ( array_key_exists('fields', $table) ) {
                foreach ( $table['fields'] as $fieldName => $field ) {
                    if ( CRM_Utils_Array::value( 'required', $field ) ||
                         CRM_Utils_Array::value( $fieldName, $this->_params['fields'] ) ) {
                        if ( $tableName == 'civicrm_address' ) {
                            $this->_addressField = true;
                        } else if ( $tableName == 'civicrm_email' ) {
                            $this->_emailField = true;
                        } else if ( $tableName == 'civicrm_phone' ) {
                            $this->_phoneField = true;
                        } else if ( $tableName == 'civicrm_activity' ) {
                            $this->_activityField = true;
                        }
                        if ($fieldName == 'street_address') {
                          $field['dbAlias'] = 
                          " CONCAT_WS(' ',
                              CONCAT_WS('<br />',
                                {$field['dbAlias']},
                                {$field['alias']}.supplemental_address_1,
                                CONCAT({$field['alias']}.city, ', ')
                              ),
                              sp.abbreviation,
                              CONCAT_WS('-', {$field['alias']}.postal_code, {$field['alias']}.postal_code_suffix), country.name)";
                        }
                        if ($fieldName == 'first_contribution_date') {
                          $field['dbAlias'] = "DATE_FORMAT({$field['alias']}.first_contribution_date, '%b %Y')";
                        }
                        if ($fieldName == 'most_recent') {
                          $field['dbAlias'] = "CONCAT('\$', {$field['alias']}.most_recent_amount, ' to ', {$field['alias']}.account, ', ', DATE_FORMAT({$field['alias']}.receive_date, '%b %Y'))";
                        }
                        $select[] = "{$field['dbAlias']} as {$tableName}_{$fieldName}";
                        $this->_columnHeaders["{$tableName}_{$fieldName}"]['title'] = $field['title'];
                        $this->_columnHeaders["{$tableName}_{$fieldName}"]['type']  = CRM_Utils_Array::value( 'type', $field );
                    }
                }
            }
        }
        $select[] =
        "GROUP_CONCAT(
          CONCAT(
            '<li><b>',
            rel.past,
            rel.type,
            '</b> ',
            rel.display_name,
            IF(alum.contact_id IS NOT NULL,
              CONCAT(' (', alum.info, ')'),
              IF(rel.end_date IS NOT NULL,
                CONCAT(
                  ' (',
                  IF(YEAR(rel.start_date) <> YEAR(rel.end_date), CONCAT(YEAR(rel.start_date), '-'), ''),
                  YEAR(rel.end_date),
                  ')'
                ),
                ''
              )
            ),
            '</li>'
          )
          SEPARATOR ''
        ) AS relationships";
        $this->_columnHeaders["relationships"]['title'] = 'Relationships';
        $this->_columnHeaders["relationships"]['type']  = NULL;
        $this->_select = "SELECT " . implode( ', ', $select ) . " ";
    }

    function from( ) {
        $this->_from = "
         FROM  civicrm_contact {$this->_aliases['civicrm_contact']} {$this->_aclFrom}";

        $this->_from .= $this->getContributionJoin();
        //used when address field is selected
        if ( $this->_addressField ) {
            $this->_from .= "
             LEFT JOIN civicrm_address {$this->_aliases['civicrm_address']}
             ON {$this->_aliases['civicrm_contact']}.id =
                {$this->_aliases['civicrm_address']}.contact_id AND
                {$this->_aliases['civicrm_address']}.is_primary = 1
             LEFT JOIN civicrm_state_province sp
             ON {$this->_aliases['civicrm_address']}.state_province_id = sp.id
             LEFT JOIN civicrm_country country
             ON {$this->_aliases['civicrm_address']}.country_id = country.id
             AND country.id <> " . USA;
        }
        //used when email field is selected
        if ( $this->_emailField ) {
            $this->_from .= "
              LEFT JOIN civicrm_email {$this->_aliases['civicrm_email']}
              ON {$this->_aliases['civicrm_contact']}.id =
                 {$this->_aliases['civicrm_email']}.contact_id AND
                 {$this->_aliases['civicrm_email']}.is_primary = 1\n";
        }
        //used when phone field is selected
        if ( $this->_phoneField ) {
            $this->_from .= $this->getPhoneJoin();
        }
        //used when activity field is selected
        if ( $this->_activityField ) {
            $this->_from .= $this->getActivityJoin();
        }
        $this->_from .= $this->getRelationships();
        $this->_from .= " LEFT JOIN civicrm_relationship spouse
                         ON spouse.relationship_type_id = 2 AND spouse.is_active = 1 AND spouse.end_date IS NULL
                         AND ({$this->_aliases['civicrm_contact']}.id = spouse.contact_id_a
                         OR {$this->_aliases['civicrm_contact']}.id = spouse.contact_id_b)\n";
    }

    function where( ) {
        $clauses = array( );
        foreach ( $this->_columns as $tableName => $table ) {
            if ( array_key_exists('filters', $table) ) {
                foreach ( $table['filters'] as $fieldName => $field ) {
                    $clause = null;
                    if ( CRM_Utils_Array::value( 'operatorType', $field ) & CRM_Utils_Type::T_DATE ) {
                        $relative = CRM_Utils_Array::value( "{$fieldName}_relative", $this->_params );
                        $from     = CRM_Utils_Array::value( "{$fieldName}_from"    , $this->_params );
                        $to       = CRM_Utils_Array::value( "{$fieldName}_to"      , $this->_params );

                        $clause = $this->dateClause( $field['name'], $relative, $from, $to, $field['type'] );
                    } else {
                        $op = CRM_Utils_Array::value( "{$fieldName}_op", $this->_params );
                        if ( $op ) {
                            $clause =
                                $this->whereClause( $field,
                                                    $op,
                                                    CRM_Utils_Array::value( "{$fieldName}_value", $this->_params ),
                                                    CRM_Utils_Array::value( "{$fieldName}_min", $this->_params ),
                                                    CRM_Utils_Array::value( "{$fieldName}_max", $this->_params ) );
                        }
                    }

                    if ( ! empty( $clause ) ) {
                        $clauses[ ] = $clause;
                    }
                }
            }
        }

        if ( empty( $clauses ) ) {
            $this->_where = " WHERE ( 1 ) ";
        } else {
            $this->_where = " WHERE " . implode( ' AND ', $clauses );
        }

        if ( $this->_aclWhere ) {
            $this->_where .= " AND {$this->_aclWhere} \n";
        }
    }

    function groupBy( ) {
        $this->_groupBy .= " GROUP BY {$this->_aliases['civicrm_contact']}.id \n";
    }

    function orderBy( ) {
        $this->_orderBy = " ORDER BY spouse.id DESC\n";
    }

    function postProcess( ) {

        //generic code
        $this->beginPostProcess( );
        $sql = $this->buildQuery( );
        $this->buildRows ( $sql, $rows );
        $this->formatDisplay( $rows );

        if ( FALSE && in_array( $this->_outputMode, array( 'print', 'pdf' ) ) ) {

            // Special formatting for print
            foreach ($rows as &$r) {
              if ($r['civicrm_contribution_most_recent_amount']) {
                $r['civicrm_contribution_most_recent_amount'] = '$' . $r['civicrm_contribution_most_recent_amount'];
              }
              if ($r['civicrm_contribution_account']) {
                $r['civicrm_contribution_account'] = '<br />to ' . $r['civicrm_contribution_account'];
              }
              if ($r['civicrm_contribution_receive_date']) {
                $r['civicrm_contribution_receive_date'] = 'on ' . date('M j, Y', strtotime($r['civicrm_contribution_receive_date']));
              }
              if ($r['civicrm_contribution_first_contribution_date']) {
                $r['civicrm_contribution_first_contribution_date'] = date('M j, Y', strtotime($r['civicrm_contribution_first_contribution_date']));
              }
              $r['header'] = $this->_formValues['report_header'];
              $r['footer'] = $this->_formValues['report_footer'];
            }

            $templateFile = 'CRM/Report/Form/Contribute/CallSheetsPrint.tpl';

            $this->doTemplateAssignment( $rows);
            $outPut = CRM_Core_Form::$_template->fetch( $templateFile );
        }

        if ( in_array( $this->_outputMode, array( 'print', 'pdf' ) ) ) {
            if ( $this->_outputMode == 'print' ) {
                echo $outPut ;
            } else {
                require_once 'CRM/Utils/PDF/Utils.php';
                CRM_Utils_PDF_Utils::html2pdf( $outPut, "CiviReport.pdf" );
            }
            CRM_Utils_System::civiExit( );
        } else {
            $this->doTemplateAssignment( $rows);
            $this->endPostProcess( $rows );
        }
    }


    function alterDisplay( &$rows ) {
        // custom code to alter rows
        $entryFound = false;
        $checkList  =  array();
        foreach ( $rows as $rowNum => $row ) {

            if ( !empty($this->_noRepeats) && $this->_outputMode != 'csv' ) {
                // not repeat contact display names if it matches with the one
                // in previous row
                $repeatFound = false;
                foreach ( $row as $colName => $colVal ) {
                    if ( CRM_Utils_Array::value( $colName, $checkList ) &&
                         is_array($checkList[$colName]) &&
                         in_array($colVal, $checkList[$colName]) ) {
                        $rows[$rowNum][$colName] = "";
                        $repeatFound = true;
                    }
                    if ( in_array($colName, $this->_noRepeats) ) {
                        $checkList[$colName][] = $colVal;
                    }
                }
            }

            if ( array_key_exists('civicrm_contact_sort_name', $row) &&
                 $rows[$rowNum]['civicrm_contact_sort_name'] &&
                 array_key_exists('civicrm_contact_id', $row) ) {
                $url = CRM_Utils_System::url( "civicrm/contact/view"  ,
                                              'reset=1&cid=' . $row['civicrm_contact_id'],
                                              $this->_absoluteUrl );
                $rows[$rowNum]['civicrm_contact_sort_name_link'] = $url;
                $rows[$rowNum]['civicrm_contact_sort_name_hover'] =
                    ts("View Contact Summary for this Contact.");
            }
            
            $rows[$rowNum]['relationships'] = '<ul>' . $row['relationships'] . '</ul>';
        }
    }

    /*
     * Get the join required to add multiple phone numbers if required. Note that a temp table is generated to join against
     *
     * @return string FROM Clause
     */
    function getPhoneJoin(){
        return " LEFT JOIN ". $this->_create_temp_phones_table('<br>') . " {$this->_aliases['civicrm_phone']} ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_phone']}.contact_id \n";
    }

    /**
     * This generates a temp table that allows the inclusion of more than one phone no.
     */
    function _create_temp_phones_table($separator = ","){
      static $tempTable;
      if(!empty($tempTable)){
        return $tempTable;
      }
       $tempTable = 'tmp_table_phone' . rand(1,999);
       CRM_Core_DAO::executeQuery(
       "CREATE TEMPORARY  TABLE $tempTable
       (SELECT contact_id , GROUP_CONCAT(CONCAT(CONCAT_WS(' x', phone, phone_ext),  ' (', IF(p.phone_type_id = 2, 'Cell', civicrm_location_type.name), ')') ORDER BY is_primary DESC SEPARATOR '$separator') as phone
       FROM civicrm_phone p
       LEFT JOIN civicrm_location_type on location_type_id = civicrm_location_type.id
       GROUP BY p.contact_id)");
       CRM_Core_DAO::executeQuery("ALTER TABLE $tempTable ADD INDEX (contact_id)");
       return $tempTable;
    }

    /*
     * Join on activity
     *
     * @return string FROM Clause
     */
    function getActivityJoin(){
        return " LEFT JOIN (
          SELECT CONCAT(IF(act.activity_type_id = 62, 'Call', 'Visit'), ' by ', cc.display_name, ' on ', DATE_FORMAT(act.activity_date_time, '%b %D, %Y'), ': <em>', ov.label, '</em>') AS activity, at.target_contact_id AS contact_id, act.details
          FROM civicrm_activity act
          INNER JOIN civicrm_contact cc ON cc.id = act.source_contact_id
          INNER JOIN civicrm_activity_target at ON at.activity_id = act.id
          LEFT JOIN civicrm_value_donor_interaction_15 di ON di.entity_id = act.id
          LEFT JOIN civicrm_option_value ov ON ov.option_group_id = 115 AND ov.value = di.call_results_53
          WHERE act.id IN (
            SELECT a.id
            FROM civicrm_activity a, civicrm_activity_target t
            WHERE a.id = t.activity_id AND a.activity_type_id IN (62,65)
            GROUP BY t.target_contact_id
            HAVING activity_date_time = MAX(activity_date_time)
          )
        ) {$this->_aliases['civicrm_activity']} ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_activity']}.contact_id \n";
    }


    /*
     * Get the join required to add relationships if required.
     * Another join finds the relation to Woolman of the relative!
     *
     * @return string FROM Clause
     */
    function getRelationships() {
        $tempTable = $this->_create_temp_relationships_table();
        return " LEFT JOIN $tempTable rel ON {$this->_aliases['civicrm_contact']}.id = rel.contact_id
        LEFT JOIN {$tempTable}_alum alum ON rel.target_contact = alum.contact_id";
    }

    /**
     * This generates a temp table that allows the inclusion of more than one relationship.
     */
    function _create_temp_relationships_table() {
      static $tempTable;
      if(!empty($tempTable)){
        return $tempTable;
      }
       $tempTable = 'tmp_table_relationship' . rand(1,999);
       $query = "SELECT r.first AS contact_id, t.label_a_b AS type, c.display_name, c.id AS target_contact, r.relationship_type_id,
       IF (r.is_active AND (r.end_date > CURDATE() OR r.end_date IS NULL), '', 'Past ') AS past, r.start_date, r.end_date
       FROM civicrm_relationship r, civicrm_contact c, civicrm_relationship_type t
       WHERE c.id = r.second AND t.id = r.relationship_type_id AND r.case_id IS NULL
       AND c.contact_type <> 'Household' AND r.relationship_type_id NOT IN (13,14,19)";
       CRM_Core_DAO::executeQuery(
       "CREATE TEMPORARY TABLE $tempTable
       (" . str_replace(array('first', 'second'), array('contact_id_a', 'contact_id_b'), $query) . ")");
       CRM_Core_DAO::executeQuery(
       "INSERT INTO $tempTable
       (" . str_replace(array('first', 'second', '_a_b'), array('contact_id_b', 'contact_id_a', '_b_a'), $query) . ")");
       // Add event participants as a pseudo relationship
       CRM_Core_DAO::executeQuery("INSERT INTO $tempTable
       (SELECT p.contact_id, 'Camper at' AS type, 'Woolman' AS display_name, ".WOOLMAN." AS target_contact, 8 AS relationship_type_id, '' AS past, MIN(e.start_date) AS start_date, MAX(e.start_date) AS end_date
       FROM civicrm_participant p, civicrm_event e
       WHERE p.event_id = e.id AND p.status_id IN (1,2,13) AND p.is_test = 0 AND e.event_type_id IN (8,9)
       GROUP BY p.contact_id)");
       CRM_Core_DAO::executeQuery("ALTER TABLE $tempTable ADD INDEX (contact_id)");
       CRM_Core_DAO::executeQuery(
       "CREATE TEMPORARY TABLE {$tempTable}_alum (
         SELECT contact_id,
           GROUP_CONCAT(
             CONCAT(
                IF(end_date IS NULL, LOWER(past), ''),
                IF(relationship_type_id = 4, 'staff', ''),
                IF(relationship_type_id = 8, 'camper', ''),
                IF(relationship_type_id = 10, 'alum', ''),
                IF(relationship_type_id = 18, 'intern', ''),
                IF(YEAR(start_date) <> YEAR(end_date),
                  CONCAT(' ', YEAR(start_date), '-'),
                  IF(end_date IS NOT NULL, ' ', '')
                ),
                IF(end_date IS NOT NULL, YEAR(end_date), '')
              )
             SEPARATOR ', '
            ) as info
         FROM $tempTable
         WHERE target_contact = ".WOOLMAN." AND relationship_type_id IN (4,8,10,18)
         GROUP BY contact_id
       )");
       CRM_Core_DAO::executeQuery("ALTER TABLE {$tempTable}_alum ADD INDEX (contact_id)");
       return $tempTable;
    }


    /*
     * Get the join required to add contribution data. Note that a temp table is generated to join against
     *
     * @return string FROM Clause
     */
    function getContributionJoin(){
        return " LEFT JOIN ". $this->_create_temp_cont_table() . " {$this->_aliases['civicrm_contribution']} ON {$this->_aliases['civicrm_contact']}.id = {$this->_aliases['civicrm_contribution']}.contact_id \n";
    }

    /**
     * This generates a temp table that aggregates contribution info.
     */
    function _create_temp_cont_table() {
      static $tempTable;
      if(!empty($tempTable)){
        return $tempTable;
      }
      $tempTable = 'tmp_table_cont' . rand(1,999);
      CRM_Core_DAO::executeQuery(
      "CREATE TEMPORARY TABLE $tempTable
      (SELECT a.contact_id, a.label as account, a.most_recent_amount, b.receive_date, b.total_amount, b.first_contribution_date, b.number_of
      FROM (
        SELECT cc.contact_id, cc.total_amount AS most_recent_amount, ov.label
        FROM civicrm_contribution cc
        LEFT JOIN civicrm_value_contribution_accounts_10 ca ON ca.entity_id = cc.id
        LEFT JOIN civicrm_option_value ov ON ca.account_18 = ov.value AND ov.option_group_id = 103
        WHERE cc.is_test = 0 AND cc.financial_type_id = 1 AND cc.contribution_status_id = 1
        ORDER BY cc.receive_date DESC
        ) a
      INNER JOIN (
        SELECT contact_id, MAX(receive_date) AS receive_date, MIN(receive_date) AS first_contribution_date, ROUND(AVG(total_amount)) AS total_amount, COUNT(id) as number_of
        FROM civicrm_contribution
        WHERE is_test = 0 AND financial_type_id = 1 AND contribution_status_id = 1
        GROUP BY contact_id
      ) b ON a.contact_id = b.contact_id
      GROUP BY a.contact_id)");
      CRM_Core_DAO::executeQuery("ALTER TABLE $tempTable ADD INDEX (contact_id)");
      return $tempTable;
    }
}
