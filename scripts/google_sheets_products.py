#!/usr/bin/env python3
import csv
import json
import sys
from pathlib import Path

from google.oauth2.credentials import Credentials
from google.auth.transport.requests import Request
from googleapiclient.discovery import build

ROOT = Path(__file__).resolve().parents[1]
EXPORT_CSV = ROOT / 'exports' / 'products-google-sheets.csv'
CONFIG_PATH = ROOT / 'config' / 'google-products-sheet.json'
TOKEN_PATH = Path('/root/.hermes/credentials/google/token_tuan_mobile_dev.json')
SCOPES = [
    'https://www.googleapis.com/auth/spreadsheets',
    'https://www.googleapis.com/auth/drive.file',
]
SHEET_TITLE = 'Laptop OSCAR - Product DB'
TAB_NAME = 'products'


def get_credentials():
    creds = Credentials.from_authorized_user_file(str(TOKEN_PATH), SCOPES)
    if creds.expired and creds.refresh_token:
        creds.refresh(Request())
        TOKEN_PATH.write_text(creds.to_json(), encoding='utf-8')
        TOKEN_PATH.chmod(0o600)
    return creds


def read_csv_rows(path):
    with path.open(newline='', encoding='utf-8') as handle:
        return list(csv.reader(handle))


def normalize_sheet_rows(rows):
    if not rows:
        return rows
    numeric_columns = {'id', 'batteryWh', 'stock', 'rating', 'reviews', 'price', 'oldPrice'}
    headers = rows[0]
    numeric_indexes = {index for index, header in enumerate(headers) if header in numeric_columns}
    normalized = [headers]
    for row_index, row in enumerate(rows[1:], start=1):
        next_row = []
        for column_index, value in enumerate(row):
            if column_index == 0 and value == '':
                next_row.append(row_index)
            elif column_index in numeric_indexes and value != '':
                try:
                    number = float(str(value).replace(',', '').strip())
                    next_row.append(int(number) if number.is_integer() else number)
                except ValueError:
                    next_row.append(value)
            else:
                next_row.append(value)
        normalized.append(next_row)
    return normalized


def get_config():
    if CONFIG_PATH.exists():
        return json.loads(CONFIG_PATH.read_text(encoding='utf-8'))
    return {}


def save_config(config):
    CONFIG_PATH.parent.mkdir(parents=True, exist_ok=True)
    CONFIG_PATH.write_text(json.dumps(config, indent=2, ensure_ascii=False) + '\n', encoding='utf-8')


def create_or_update():
    if not EXPORT_CSV.exists():
        raise SystemExit(f'Missing CSV: {EXPORT_CSV}. Run npm run products:export first.')

    creds = get_credentials()
    sheets = build('sheets', 'v4', credentials=creds)
    drive = build('drive', 'v3', credentials=creds)
    config = get_config()
    spreadsheet_id = config.get('spreadsheetId')

    if not spreadsheet_id:
        spreadsheet = sheets.spreadsheets().create(
            body={
                'properties': {'title': SHEET_TITLE},
                'sheets': [{'properties': {'title': TAB_NAME}}],
            },
            fields='spreadsheetId,spreadsheetUrl',
        ).execute()
        spreadsheet_id = spreadsheet['spreadsheetId']
        config = {
            'spreadsheetId': spreadsheet_id,
            'spreadsheetUrl': spreadsheet['spreadsheetUrl'],
            'tabName': TAB_NAME,
        }
        save_config(config)
    else:
        spreadsheet = sheets.spreadsheets().get(spreadsheetId=spreadsheet_id, fields='spreadsheetUrl').execute()
        config.setdefault('spreadsheetUrl', spreadsheet['spreadsheetUrl'])
        config.setdefault('tabName', TAB_NAME)
        save_config(config)

    rows = normalize_sheet_rows(read_csv_rows(EXPORT_CSV))
    sheets.spreadsheets().values().clear(
        spreadsheetId=spreadsheet_id,
        range=f"{config['tabName']}!A:ZZ",
        body={},
    ).execute()
    sheets.spreadsheets().values().update(
        spreadsheetId=spreadsheet_id,
        range=f"{config['tabName']}!A1",
        valueInputOption='RAW',
        body={'values': rows},
    ).execute()

    sheet_meta = sheets.spreadsheets().get(spreadsheetId=spreadsheet_id).execute()
    sheet_id = next(s['properties']['sheetId'] for s in sheet_meta['sheets'] if s['properties']['title'] == config['tabName'])
    row_count = len(rows)
    col_count = len(rows[0]) if rows else 0
    requests = [
        {
            'updateSheetProperties': {
                'properties': {
                    'sheetId': sheet_id,
                    'gridProperties': {'frozenRowCount': 1, 'frozenColumnCount': 1},
                },
                'fields': 'gridProperties.frozenRowCount,gridProperties.frozenColumnCount',
            }
        },
        {
            'repeatCell': {
                'range': {'sheetId': sheet_id, 'startRowIndex': 0, 'endRowIndex': 1, 'startColumnIndex': 0, 'endColumnIndex': col_count},
                'cell': {
                    'userEnteredFormat': {
                        'backgroundColor': {'red': 0.05, 'green': 0.22, 'blue': 0.36},
                        'horizontalAlignment': 'CENTER',
                        'textFormat': {'foregroundColor': {'red': 1, 'green': 1, 'blue': 1}, 'bold': True},
                    }
                },
                'fields': 'userEnteredFormat(backgroundColor,textFormat,horizontalAlignment)',
            }
        },
        {
            'repeatCell': {
                'range': {'sheetId': sheet_id, 'startRowIndex': 1, 'endRowIndex': row_count, 'startColumnIndex': 0, 'endColumnIndex': col_count},
                'cell': {
                    'userEnteredFormat': {
                        'verticalAlignment': 'MIDDLE',
                        'wrapStrategy': 'WRAP',
                        'backgroundColor': {'red': 0.98, 'green': 0.99, 'blue': 1},
                    }
                },
                'fields': 'userEnteredFormat(verticalAlignment,wrapStrategy,backgroundColor)',
            }
        },
        {
            'repeatCell': {
                'range': {'sheetId': sheet_id, 'startRowIndex': 1, 'endRowIndex': row_count, 'startColumnIndex': 0, 'endColumnIndex': 1},
                'cell': {'userEnteredFormat': {'horizontalAlignment': 'CENTER', 'textFormat': {'bold': True}}},
                'fields': 'userEnteredFormat(horizontalAlignment,textFormat)',
            }
        },
        {
            'repeatCell': {
                'range': {'sheetId': sheet_id, 'startRowIndex': 1, 'endRowIndex': row_count, 'startColumnIndex': 17, 'endColumnIndex': 19},
                'cell': {'userEnteredFormat': {'numberFormat': {'type': 'NUMBER', 'pattern': '#,##0'}}},
                'fields': 'userEnteredFormat.numberFormat',
            }
        },
        {
            'setBasicFilter': {
                'filter': {
                    'range': {'sheetId': sheet_id, 'startRowIndex': 0, 'endRowIndex': row_count, 'startColumnIndex': 0, 'endColumnIndex': col_count},
                    'sortSpecs': [{'dimensionIndex': 0, 'sortOrder': 'ASCENDING'}],
                }
            }
        },
        {'autoResizeDimensions': {'dimensions': {'sheetId': sheet_id, 'dimension': 'COLUMNS', 'startIndex': 0, 'endIndex': col_count}}},
    ]
    sheets.spreadsheets().batchUpdate(spreadsheetId=spreadsheet_id, body={'requests': requests}).execute()

    # Keep it owned by the OAuth account, but make the direct link usable for anyone with it.
    try:
        drive.permissions().create(
            fileId=spreadsheet_id,
            body={'type': 'anyone', 'role': 'writer'},
            fields='id',
        ).execute()
    except Exception as exc:
        print(f'Warning: could not set link sharing: {exc}', file=sys.stderr)

    print(json.dumps({'count': len(rows) - 1, **config}, ensure_ascii=False))


def download_to_csv():
    config = get_config()
    spreadsheet_id = config.get('spreadsheetId')
    tab_name = config.get('tabName', TAB_NAME)
    if not spreadsheet_id:
        raise SystemExit(f'Missing spreadsheetId in {CONFIG_PATH}')
    creds = get_credentials()
    sheets = build('sheets', 'v4', credentials=creds)
    result = sheets.spreadsheets().values().get(spreadsheetId=spreadsheet_id, range=f'{tab_name}!A:ZZ').execute()
    values = result.get('values', [])
    EXPORT_CSV.parent.mkdir(parents=True, exist_ok=True)
    with EXPORT_CSV.open('w', newline='', encoding='utf-8') as handle:
        writer = csv.writer(handle)
        writer.writerows(values)
    print(json.dumps({'count': max(len(values) - 1, 0), 'csv': str(EXPORT_CSV), **config}, ensure_ascii=False))


if __name__ == '__main__':
    command = sys.argv[1] if len(sys.argv) > 1 else 'push'
    if command == 'push':
        create_or_update()
    elif command == 'pull':
        download_to_csv()
    else:
        raise SystemExit('Usage: google_sheets_products.py [push|pull]')
