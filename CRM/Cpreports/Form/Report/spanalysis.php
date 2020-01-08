<?php
use CRM_Cpreports_ExtensionUtil as E;

class CRM_Cpreports_Form_Report_spanalysis extends CRM_Report_Form {

  protected $_addressField = FALSE;

  protected $_emailField = FALSE;

  protected $_summary = NULL;

  protected $_customGroupExtends = array('Individual','Contact','Relationship');

  protected $_customGroupGroupBy = FALSE;

  protected $_serviceDateTo;
  protected $_serviceDateFrom;


  function __construct() {
    // Build a list of options for the nick_name select filter (all existing team nicknames)
    $nickNameOptions = array();
    $dao = CRM_Core_DAO::executeQuery('
      SELECT DISTINCT nick_name
      FROM civicrm_contact
      WHERE
        contact_type = "Organization"
        AND contact_sub_type LIKE "%team%"
        AND nick_name > ""
      ORDER BY nick_name
    ');
    while ($dao->fetch()) {
      $nickNameOptions[$dao->nick_name] = $dao->nick_name;
    }

    $this->_columns = array(
      'civicrm_contact_indiv' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'sort_name' => array(
            'title' => E::ts('Contact Name'),
            'required' => TRUE,
            'default' => TRUE,
            'no_repeat' => TRUE,
          ),
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
          'contact_id' => array(
            'title' => E::ts('Contact ID'),
            'name' => 'id',
          ),
          'first_name' => array(
            'title' => E::ts('First Name'),
          ),
          'last_name' => array(
            'title' => E::ts('Last Name'),
          ),
          'middle_name' => array(
            'title' => E::ts('Middle Name'),
          ),
          'gender_id' => array(
            'title' => E::ts('Gender'),
          ),
          'prefix_id' => array(
            'title' => E::ts('Prefix'),
          ),
          'birth_date' => array(
            'title' => E::ts('Date of Birth'),
          ),
        ),
        'filters' => array(
          'sort_name' => array(
            'title' => E::ts('Contact Name'),
            'operator' => 'like',
          ),
        ),
        'order_bys' => array(
          'sort_name' => array(
            'title' => E::ts('Contact Name'),
          ),
        ),
        'grouping' => 'contact-fields',
      ),
      'civicrm_contact_team' => array(
        'dao' => 'CRM_Contact_DAO_Contact',
        'fields' => array(
          'organization_name' => array(
            'title' => E::ts('Team Name'),
            'required' => FALSE,
            'default' => TRUE,
            'grouping' => 'team-fields',
          ),
          'nick_name' => array(
            'title' => E::ts('Team Nickname'),
            'required' => FALSE,
            'default' => TRUE,
            'grouping' => 'team-fields',
          ),
          'id' => array(
            'no_display' => TRUE,
            'required' => TRUE,
          ),
        ),
        'filters' => array(
          'organization_name' => array(
            'title' => E::ts('Team Name'),
            'operator' => 'like',
            'type' =>	CRM_Utils_Type::T_STRING,
          ),
          'nick_name_like' => array(
            'title' => E::ts('Team Nickname'),
            'dbAlias' => 'contact_team_civireport.nick_name',
            'operator' => 'like',
            'type' =>	CRM_Utils_Type::T_STRING,
          ),
          'nick_name_select' => array(
            'title' => E::ts('Team Nickname'),
            'dbAlias' => 'contact_team_civireport.nick_name',
            'operatorType' => CRM_Report_Form::OP_MULTISELECT,
            'options' => $nickNameOptions,
            'type' =>	CRM_Utils_Type::T_STRING,
          ),
        ),
        'order_bys' => array(
          'organization_name' => array(
            'title' => E::ts('Team Name'),
          ),
        ),
        'grouping' => 'contact-fields',
      ),
      'civicrm_relationship' => array(
        'fields' => array(
          'start_date' => array(
            'title' => E::ts('Start Date'),
            'default' => TRUE,
          ),
          'end_date' => array(
            'title' => E::ts('End Date'),
            'default' => TRUE,
          ),
          'days_active' => array(
            'title' => E::ts('Days Active'),
            'dbAlias' => 'IF (start_date IS NOT NULL, DATEDIFF(IFNULL(end_date, NOW()), start_date), "")',
            'default' => TRUE,
          ),
        ),
        'filters' => array(
          'service_dates' => array(
            'title' => E::ts('Service dates'),
            'pseudofield' => TRUE,
            'type' => 	CRM_Utils_Type::T_DATE,
            'operatorType' => CRM_Report_Form::OP_DATE,
          ),
        ),
        'grouping' => 'relationship-fields',
      ),
      'civicrm_note' => array(
        'fields' => array(
          'note' => array(
            'title' => E::ts('Note'),
            'default' => TRUE,
          ),
        ),
        'grouping' => 'relationship-fields',
      ),
    );
    $this->_groupFilter = TRUE;
    $this->_tagFilter = TRUE;

    parent::__construct();
  }

  function from() {
    $this->_aliases['civicrm_contact'] = $this->_aliases['civicrm_contact_indiv'];
    
    $this->_from = "
      FROM  civicrm_contact {$this->_aliases['civicrm_contact_indiv']} {$this->_aclFrom}
        INNER JOIN civicrm_relationship {$this->_aliases['civicrm_relationship']}
          ON {$this->_aliases['civicrm_relationship']}.contact_id_b  = {$this->_aliases['civicrm_contact_indiv']}.id
        INNER JOIN civicrm_relationship_type rt
          ON {$this->_aliases['civicrm_relationship']}.relationship_type_id = rt.id
          AND rt.name_a_b = 'Has_team_volunteer'
        INNER JOIN civicrm_contact {$this->_aliases['civicrm_contact_team']}
          ON {$this->_aliases['civicrm_contact_team']}.id = {$this->_aliases['civicrm_relationship']}.contact_id_a
    ";

    if ($this->isTableSelected('civicrm_note')) {
      $this->_from .= "
        LEFT JOIN civicrm_note {$this->_aliases['civicrm_note']}
          ON {$this->_aliases['civicrm_note']}.entity_table = 'civicrm_relationship'
            AND {$this->_aliases['civicrm_note']}.entity_id = {$this->_aliases['civicrm_relationship']}.id
      ";
    }

    $this->_from .= "
      -- end from()

    ";
  }

  function storeWhereHavingClauseArray() {
    parent::storeWhereHavingClauseArray();

    // Convert service_dates 'from' and 'to' params into max start date and min end date, respectively.
    list($from, $to) = $this->getFromTo($this->_params['service_dates_relative'], $this->_params['service_dates_from'], $this->_params['service_dates_to']);
    if ($to) {
      $this->_serviceDateTo = $to;
      $this->_whereClauses[] = "( start_date <= {$this->_serviceDateTo} )";
    }
    if ($from) {
      $this->_serviceDateFrom = $from;
      $this->_whereClauses[] = "( end_date IS NULL OR end_date >= {$this->_serviceDateFrom} )";
    }
  }

  function postProcess() {

    $this->beginPostProcess();

    foreach (array('relative', 'from', 'to') as $suffix) {
      $this->_params["end_date_". $suffix] = $this->_params["start_date_". $suffix] = $this->_params["service_dates_". $suffix];
    }

    // get the acl clauses built before we assemble the query
    $this->buildACLClause($this->_aliases['civicrm_contact_indiv']);
    $sql = $this->buildQuery(TRUE);

    $rows = array();
    $this->buildRows($sql, $rows);

    $this->formatDisplay($rows);
    $this->doTemplateAssignment($rows);
    $this->endPostProcess($rows);
  }

  function alterDisplay(&$rows) {
    // custom code to alter rows
    $entryFound = FALSE;
    foreach ($rows as $rowNum => $row) {

      if (array_key_exists('civicrm_contact_indiv_gender_id', $row)) {
        if ($value = $row['civicrm_contact_indiv_gender_id']) {
          $rows[$rowNum]['civicrm_contact_indiv_gender_id'] = CRM_Core_PseudoConstant::getLabel('CRM_Contact_DAO_Contact', 'gender_id', $value);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_contact_indiv_prefix_id', $row)) {
        if ($value = $row['civicrm_contact_indiv_prefix_id']) {
          $rows[$rowNum]['civicrm_contact_indiv_prefix_id'] = CRM_Core_PseudoConstant::getLabel('CRM_Contact_DAO_Contact', 'prefix_id', $value);
        }
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_contact_indiv_sort_name', $row) &&
        $rows[$rowNum]['civicrm_contact_indiv_sort_name'] &&
        array_key_exists('civicrm_contact_indiv_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['civicrm_contact_indiv_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contact_indiv_sort_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_indiv_sort_name_hover'] = E::ts("View Contact Summary for this Contact.");
        $entryFound = TRUE;
      }

      if (array_key_exists('civicrm_contact_team_organization_name', $row) &&
        $rows[$rowNum]['civicrm_contact_team_organization_name'] &&
        array_key_exists('civicrm_contact_team_id', $row)
      ) {
        $url = CRM_Utils_System::url("civicrm/contact/view",
          'reset=1&cid=' . $row['civicrm_contact_team_id'],
          $this->_absoluteUrl
        );
        $rows[$rowNum]['civicrm_contact_team_organization_name_link'] = $url;
        $rows[$rowNum]['civicrm_contact_team_organization_name_hover'] = E::ts("View Contact Summary for this Contact.");
        $entryFound = TRUE;
      }

      if (!$entryFound) {
        break;
      }
    }
  }

  public function statistics(&$rows) {
    $statistics = parent::statistics($rows);
    // Get an abbreviated form of the report SQL, and use it to get a count of
    // distinct team contact_ids
    $sqlBase = " {$this->_from} {$this->_where} {$this->_groupBy} {$this->_having}";

    //Service Providers active at start of analysis period
    $activeStartWhere = "";
    if ($this->_serviceDateFrom) {
      $activeStartWhere = "start_date < {$this->_serviceDateFrom} AND ";
      $query = "select count(distinct contact_id_b) from civicrm_relationship where $activeStartWhere id IN (SELECT {$this->_aliases['civicrm_relationship']}.id {$sqlBase})";
      // dsm($query, "-- active start\n");
      $activeStartCount = CRM_Core_DAO::singleValueQuery($query);
    }
    else {
      // No "from" date means the beginning of time, when zero volunteers were active.
      $activeStartCount = 0;
      // dsm(0, 'active_start');
    }
    $statistics['counts']['active_start'] = array(
      'title' => ts("Service Providers active at start of analysis period"),
      'value' => $activeStartCount,
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );

    //Service Providers terminated during analysis period
    if ($this->_serviceDateTo) {
      $toDateSql = "'{$this->_serviceDateTo}'";
    }
    else {
      $toDateSql = 'now()';
    }
    $query = "
      select count(distinct contact_id_b)
      from (
       select contact_id_b, max(ifnull(end_date, now() + interval 1 day)) as max_end_date
       from (
         select {$this->_aliases['civicrm_relationship']}.* {$sqlBase}
        ) t1
        group by contact_id_b
        having max_end_date <= $toDateSql
      ) t2
    ";
    // dsm($query, "-- terminated during\n");
    $statistics['counts']['ended_during'] = array(
      'title' => ts("Service Providers terminated during analysis period"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );

    //Service Providers enlisted during analysis period
    if ($this->_serviceDateFrom) {
      $query = "
        select count(distinct contact_id_b)
        from (
         select contact_id_b, min(start_date) as min_start_date
         from (
           select {$this->_aliases['civicrm_relationship']}.* {$sqlBase}
          ) t1
          group by contact_id_b
          having min_start_date >= '{$this->_serviceDateFrom}'
        ) t2
      ";
    }
    else {
      $query = "select count(distinct contact_id_b) {$sqlBase}";
    }
    // dsm($query, "-- enlisted during\n");
    $statistics['counts']['started_during'] = array(
      'title' => ts("Service Providers enlisted during analysis period"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );

    //Service Providers active at end of analysis period
    if ($this->_serviceDateTo) {
      $activeEndWhere = "(end_date IS NULL OR end_date > {$this->_serviceDateTo}) AND ";
    }
    else {
      // No "to" date means the end of time, when only volunteers with no end_date will be active
      $activeEndWhere = "(end_date IS NULL) AND ";
    }
    $query = "select count(distinct contact_id_b) from civicrm_relationship where {$activeEndWhere} id IN (SELECT {$this->_aliases['civicrm_relationship']}.id {$sqlBase})";
    // dsm($query, "-- active end\n");
    $statistics['counts']['active_end'] = array(
      'title' => ts("Service Providers active at end of analysis period"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );

    //Net change in active Service Providers
    $statistics['counts']['net_change'] = array(
      'title' => ts("Net change in active Service Providers"),
      'value' => ($statistics['counts']['active_end']['value'] - $statistics['counts']['active_start']['value']),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );

    //Total Service Providers processed (Active and Terminated)
    $query = "select count(distinct contact_id_b) from civicrm_relationship where id IN (SELECT {$this->_aliases['civicrm_relationship']}.id {$sqlBase})";
    $statistics['counts']['total_processed'] = array(
      'title' => ts("Total Service Providers processed (Active and Terminated)"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );

    //Total composite duration of all service providers (days)
    $query = "select sum({$this->_columns['civicrm_relationship']['fields']['days_active']['dbAlias']}) {$sqlBase}";
    $statistics['counts']['total_days'] = array(
      'title' => ts("Total composite duration of all service providers (days)"),
      'value' => CRM_Core_DAO::singleValueQuery($query),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );

    //Average duration (based on all Service Providers processed
    $statistics['counts']['average_duration'] = array(
      'title' => ts("Average duration (based on all Service Providers processed)"),
      'value' => ($statistics['counts']['total_processed']['value'] ? ($statistics['counts']['total_days']['value'] / $statistics['counts']['total_processed']['value']) : 0),
      'type' => CRM_Utils_Type::T_INT  // e.g. CRM_Utils_Type::T_STRING, default seems to be integer
    );


    return $statistics;
  }

}
