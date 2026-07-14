# Product Google Sheets Sync Rule

Website nay dang dung `src/data.js` lam database san pham tinh. Google Sheet ben duoi la nguon chinh de chinh sua san pham hang loat.

- Sheet: https://docs.google.com/spreadsheets/d/1jsokRNs07N6ZVxiwLyYYwpXJZmRXn-5EZKfgA0wWut8/edit
- Spreadsheet ID: `1jsokRNs07N6ZVxiwLyYYwpXJZmRXn-5EZKfgA0wWut8`
- Tab: `products`
- Local config: `config/google-products-sheet.json`
- OAuth token: `/root/.hermes/credentials/google/token_tuan_mobile_dev.json`

## Xuat san pham tu website len Google Sheets

```bash
npm run products:export
npm run products:sheet:push
```

Lenh nay doc `src/data.js`, tao/cap nhat `exports/products-google-sheets.csv`, roi day du lieu len tab `products`.

## Cot duoc dong bo

`id`, `name`, `category`, `brand`, `type`, `cpu`, `ram`, `ssd`, `screen`, `batteryWh`, `batteryRuntime`, `demand`, `stock`, `rating`, `reviews`, `promo_vi`, `promo_en`, `price`, `oldPrice`, `condition_vi`, `condition_en`, `badge_vi`, `badge_en`, `specs_vi`, `specs_en`, `color`, `image`, `sourceUrl`.

Quy uoc:

- `id` la khoa chinh dang number; giu nguyen neu chi sua thong tin san pham.
- `gpu` chi ghi card roi; neu la onboard/iGPU thi de trong de website khong hien GPU.
- `variants_json` luu cac option cau hinh/gia theo JSON khi mot may co nhieu cau hinh CPU/RAM/GPU/SSD.
- `price`, `oldPrice`, `stock`, `batteryWh`, `rating`, `reviews` la so.
- `specs_vi` va `specs_en` cach nhau bang dau ` | `.
- Khong doi ten cot neu muon sync tu Sheet ve website.
- San pham moi can co `id` khong trung. San pham bi xoa khoi Sheet se bi xoa khoi website sau khi sync.

## Dong bo tu Google Sheets ve website

Khi Boss bao da sua Sheet va muon dong bo website, chay:

```bash
npm run products:sheet:pull
npm run products:sync
npm run build
```

Lenh `products:sheet:pull` keo Sheet ve `exports/products-google-sheets.csv`. Lenh `products:sync` ghi lai `src/data.js`.

## Rule da luu

Khi user bao "dong bo sheet voi website" hoac tuong tu: vao `/root/old-laptop-shop`, chay `npm run products:sheet:pull`, `npm run products:sync`, `npm run build`, roi bao lai so san pham da dong bo va build pass/fail.
