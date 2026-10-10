/******************************************************
 BASIK Receivables Portal - Google Sheet write-back API

 Paste this doPost(e) function in your existing Apps Script
 project and redeploy the Web App. Keep your existing doGet(e)
 that returns live sheet JSON.

 Required Web App settings:
 Execute as: Me
 Who has access: Anyone
 URL format: /exec?key=BASIK2026
******************************************************/

const BASIK_SECRET_KEY = 'BASIK2026';
const BASIK_DATA_SHEET = 'Data';
const BASIK_HEADER_ROW = 3;

function basikJson_(obj) {
  return ContentService
    .createTextOutput(JSON.stringify(obj))
    .setMimeType(ContentService.MimeType.JSON);
}

function doPost(e) {
  try {
    const body = e && e.postData && e.postData.contents
      ? JSON.parse(e.postData.contents)
      : {};

    const key = (e && e.parameter && e.parameter.key) || body.key || '';
    if (key !== BASIK_SECRET_KEY) {
      return basikJson_({ success: false, message: 'Invalid key' });
    }

    if (body.action === 'updateCell') {
      return basikUpdateCell_(body);
    }

    return basikJson_({
      success: false,
      message: 'Unknown action. Use action=updateCell.'
    });

  } catch (err) {
    return basikJson_({
      success: false,
      message: err && err.message ? err.message : String(err)
    });
  }
}

function basikUpdateCell_(body) {
  const ss = SpreadsheetApp.getActiveSpreadsheet();
  const sheet = ss.getSheetByName(body.sheet || BASIK_DATA_SHEET);
  if (!sheet) {
    return basikJson_({ success: false, message: 'Data sheet not found' });
  }

  const row = Number(body.row || 0);
  if (!row || row <= BASIK_HEADER_ROW) {
    return basikJson_({ success: false, message: 'Invalid data row' });
  }

  const headerName = String(body.header || 'Action Point').trim();
  if (!headerName) {
    return basikJson_({ success: false, message: 'Header is required' });
  }

  const lastCol = Math.max(1, sheet.getLastColumn());
  const headers = sheet
    .getRange(BASIK_HEADER_ROW, 1, 1, lastCol)
    .getValues()[0]
    .map(v => String(v || '').trim());

  const norm = s => String(s || '').trim().toLowerCase().replace(/\s+/g, ' ');
  const aliases = [headerName, 'Action Point', 'Action / follow-up', 'Follow up', 'Follow-up', 'Followup'];
  let col = headers.findIndex(h => aliases.map(norm).includes(norm(h))) + 1;

  if (!col) {
    col = lastCol + 1;
    sheet.getRange(BASIK_HEADER_ROW, col).setValue(headerName);
  }

  sheet.getRange(row, col).setValue(body.value == null ? '' : body.value);

  return basikJson_({
    success: true,
    action: 'updateCell',
    sheet: sheet.getName(),
    row: row,
    column: col,
    header: headerName,
    value: body.value == null ? '' : body.value
  });
}
