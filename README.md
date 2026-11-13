# Clinic Queue API

REST API สำหรับระบบบัตรคิวโรงพยาบาล/คลินิก: ตู้กดบัตรคิว, จอแสดงคิว, โทรศัพท์ผู้ป่วยเช็กคิว และเคาน์เตอร์เจ้าหน้าที่เรียกคิว

**Laravel 13 · PHP 8.5 · Sanctum · SQLite/PostgreSQL · Docker · PHPUnit (21 tests) · Pint · GitHub Actions**

## จุดที่ออกแบบไว้

- **เลขคิวไม่ซ้ำแม้กดพร้อมกัน**: ออกเลข `A001, A002, …` ใน transaction และ lock แถวของบริการ (`lockForUpdate`) ทำให้ตู้หลายตู้ไม่ได้เลขซ้ำ และเลขคิวเริ่มใหม่ทุกวัน
- **เจ้าหน้าที่สองเคาน์เตอร์ไม่เรียกคนเดียวกัน**: `call-next` lock คิวแรกที่รออยู่ก่อนเปลี่ยนสถานะ
- **สถานะเดินตามลำดับเท่านั้น**: `waiting → called → serving → done` (หรือ `skipped` / `cancelled`) กำหนดไว้ใน enum `TicketStatus::canTransitionTo()` ส่งสถานะที่ข้ามขั้นจะได้ 422
- **ผู้ป่วยไม่ต้องล็อกอิน**: เช็กคิวด้วย UUID บนบัตร (ไม่ใช้ id ที่เดาได้) พร้อมจำนวนคนข้างหน้าและเวลารอโดยประมาณ
- **สิทธิ์ตามบทบาท**: Staff เรียกคิวได้ · Admin จัดการบริการและดูสถิติ · rate limit ที่ login และตู้กดบัตร

## API (`/api/v1`)

| Method | Path | ใคร | ทำอะไร |
|---|---|---|---|
| POST | `/auth/login` | ทุกคน | รับ token (Sanctum) |
| GET | `/services` | ทุกคน | รายการบริการ |
| POST | `/tickets` | ตู้กดบัตร | ออกบัตรคิว → เลขคิว + คนข้างหน้า + เวลารอ |
| GET | `/tickets/{uuid}` | ผู้ป่วย | เช็กสถานะคิวตัวเอง |
| GET | `/services/{id}/board` | จอแสดงคิว | กำลังเรียก/ให้บริการ + 5 คิวถัดไป |
| POST | `/services/{id}/call-next` | Staff | เรียกคิวถัดไปไปที่เคาน์เตอร์ |
| PATCH | `/tickets/{uuid}/status` | Staff | `serving` / `done` / `skipped` / `waiting` |
| POST, PATCH | `/services` | Admin | เพิ่ม/แก้บริการ |
| GET | `/stats/today` | Admin | สถิติวันนี้ + เวลารอเฉลี่ย |

ตัวอย่าง:

```bash
curl -X POST localhost:8000/api/v1/tickets -H 'Content-Type: application/json' -d '{"service_id":1}'
# {"data":{"id":"9b1c…","number":"A001","status":"waiting",…},"ahead":0,"estimated_wait_minutes":0}
```

## รัน

```bash
docker build -t clinic-queue-api .
docker run -p 8000:8000 -e SEED_DEMO=1 clinic-queue-api
```

บัญชีทดลอง (ข้อมูลสมมติ): `admin@example.com` / `password`, `staff@example.com` / `password`

ทดสอบ: `php artisan test` · ตรวจโค้ด: `./vendor/bin/pint --test`
