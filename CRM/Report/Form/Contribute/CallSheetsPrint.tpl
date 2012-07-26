<html>
  <head>
    <title>Call Sheets</title>
    <style type="text/css">@import url(http://playpen.dev/sites/all/modules/civicrm/css/print.css);</style>
  </head>
  <body><div id="crm-container">

  {foreach from=$rows item=row key=rowid}

  <div style="page-break-after:always; font-size:1.2em;">
    <div>Contact ID: <strong>{$row.civicrm_contact_id}</strong></div>
    <h2>Call sheet for {$row.civicrm_contact_display_name}</h2>
    {$row.header}
    <table class="report-layout" style="width:100%;">
      <tr>
        <td style="vertical-align:top;">
          <strong>Phone</strong> <br />
          {$row.civicrm_phone_phone}
        </td>
        <td style="vertical-align:top;">
          <strong>Address</strong> <br />
          {$row.civicrm_address_street_address} <br />
          {$row.civicrm_address_city}, {$row.civicrm_address_state_province_id} {$row.civicrm_address_postal_code}
        </td>
        <td style="vertical-align:top;">
          <strong>Email</strong> <br />
          {$row.civicrm_email_email}
        </td>
      </tr>
      <tr>
        <td style="vertical-align:top;">
          {$row.relationships}
        </td>
        <td style="vertical-align:top;">
          <strong>Last Donation:</strong><br />
          {$row.civicrm_contribution_most_recent_amount}
          {$row.civicrm_contribution_receive_date}
          {$row.civicrm_contribution_account}
        </td>
        <td style="vertical-align:top;">
          <strong>Donor Since</strong> <br />
          {$row.civicrm_contribution_first_contribution_date} <br />
          <strong>Total Donations</strong> <br />
          {$row.civicrm_contribution_number_of}
        </td>
      </tr>
    </table>
  {$row.footer}
  </div>
  {/foreach}
  </div>
</body>
</html>
