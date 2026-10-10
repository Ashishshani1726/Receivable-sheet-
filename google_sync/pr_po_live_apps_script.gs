/*
Basik PR / PO live Google Sheet API
Paste this code in Extensions > Apps Script for your PR/PO Google Sheet, deploy as Web App,
then paste the Web App URL in config.php => PR_PO_SHEET_API_URL.

Portal URL already configured with:
https://script.google.com/macros/s/AKfycbwglEv4_MnNXyq0Ca-C5bZT3WwILCXr6StorUZvGmGTYcTgd7i-csIEutKIirE19D1aQQ/exec?key=BASIK2026&sheet=Sheet55
*/

const PR_PO_API_KEY = 'BASIK2026';
const PR_PO_SPREADSHEET_ID = '1_4aVIV9GrJzdVNtBWH0hEuQrPi216NensynFFBH9Mpk';
const PR_PO_DEFAULT_SHEET = 'Sheet55';

function doGet(e) {
  try {
    e = e || { parameter: {} };
    const key = e.parameter.key || '';
    if (key !== PR_PO_API_KEY) {
      return jsonOutput_({ success: false, error: 'Invalid API key' });
    }

    const sheetName = e.parameter.sheet || PR_PO_DEFAULT_SHEET;
    let ss;
    try {
      ss = SpreadsheetApp.openById(PR_PO_SPREADSHEET_ID);
    } catch (err) {
      ss = SpreadsheetApp.getActiveSpreadsheet();
    }

    const sheet = ss.getSheetByName(sheetName);
    if (!sheet) {
      return jsonOutput_({
        success: false,
        error: 'Sheet not found: ' + sheetName,
        availableSheets: ss.getSheets().map(s => s.getName())
      });
    }

    const range = sheet.getDataRange();
    const displayValues = range.getDisplayValues();
    const rawValues = range.getValues();
    const headerRowIndex = findHeaderRow_(displayValues);
    const headers = displayValues[headerRowIndex].map(h => cleanHeader_(h));
    const timezone = Session.getScriptTimeZone() || 'Asia/Kolkata';
    const rows = [];

    for (let i = headerRowIndex + 1; i < displayValues.length; i++) {
      const displayRow = displayValues[i];
      if (displayRow.every(v => String(v).trim() === '')) continue;
      rows.push({
        row: i + 1,
        values: rawValues[i].map(v => formatApiCell_(v, timezone)),
        displayValues: displayRow,
        _rowNumber: i + 1
      });
    }

    return jsonOutput_({
      success: true,
      source: 'PR_PO_LIVE_SHEET',
      sourceFile: ss.getName(),
      spreadsheetName: ss.getName(),
      spreadsheetId: ss.getId ? ss.getId() : PR_PO_SPREADSHEET_ID,
      sheetName: sheetName,
      sheet: sheetName,
      headerRow: headerRowIndex + 1,
      asOfDate: Utilities.formatDate(new Date(), Session.getScriptTimeZone() || 'Asia/Kolkata', 'yyyy-MM-dd'),
      rowCount: rows.length,
      headers: headers,
      rows: rows
    });
  } catch (err) {
    return jsonOutput_({ success: false, error: err && err.message ? err.message : String(err) });
  }
}

function findHeaderRow_(values) {
  const keywords = ['PR NO', 'PR DATE', 'PO NO', 'PO DATE', 'PO QTY', 'GRN', 'GRN DATE'];
  let bestRow = 0;
  let bestScore = -1;

  for (let r = 0; r < Math.min(values.length, 25); r++) {
    const text = values[r].map(v => String(v).trim().toUpperCase()).join('|');
    let score = 0;
    keywords.forEach(k => { if (text.includes(k)) score++; });
    if (score > bestScore) {
      bestScore = score;
      bestRow = r;
    }
  }
  return bestRow;
}

function cleanHeader_(value) {
  return String(value || '').trim().replace(/\s+/g, ' ');
}

function formatApiCell_(value, timezone) {
  if (Object.prototype.toString.call(value) === '[object Date]' && !isNaN(value.getTime())) {
    return Utilities.formatDate(value, timezone, 'yyyy-MM-dd HH:mm:ss');
  }
  return value === null || value === undefined ? '' : value;
}

function jsonOutput_(data) {
  return ContentService
    .createTextOutput(JSON.stringify(data))
    .setMimeType(ContentService.MimeType.JSON);
}
