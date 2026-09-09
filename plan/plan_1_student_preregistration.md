# প্ল্যান ১ — Student Pre-Registration System

## উদ্দেশ্য

ViretaDev-এ শুধুমাত্র আগে-থেকে-approved student list-এর শিক্ষার্থীরা account খুলতে পারবে। Organization আগে থেকে `reg_no + phone` দিয়ে eligible list রাখে; সেই দুটোর সাথে মিললে তবেই কেউ register করতে পারে। এতে random/অযাচিত signup আটকানো যায়।

---

## 1. ডাটাবেস

### টেবিল: `students` (২০ কলাম)

**গ্রুপ A — Pre-seeded (organization আগে দিয়ে রাখবে):**

| Column | Type | Constraint |
|--------|------|-----------|
| `id` | bigint | PK |
| `educational_registration_no` | varchar(60) | UNIQUE (validation key) |
| `phone_no` | varchar(20) | index (validation key) |
| `batch_id` | bigint | FK → batches |
| `status` | enum | pending / registered / blocked |
| `registered_at` | timestamp | null → registration-এ set |
| `created_at` / `updated_at` | timestamp | auto |

**গ্রুপ B — Registration-এ পূরণ হবে (সব null, তারপর fill):**

| Column | Type |
|--------|------|
| `name` | varchar(100) |
| `father_name` | varchar(100) |
| `mother_name` | varchar(100) |
| `email` | varchar(190) UNIQUE |
| `address` | text |
| `pro_pic` | varchar(255) |
| `ssc_roll` | varchar(30) |
| `ssc_registration` | varchar(30) |
| `whatsapp_number` | varchar(20) |
| `emergency_phone` | varchar(20) |
| `date_of_birth` | date |
| `gender` | enum(male/female/other) |
| `blood_group` | varchar(10) |
| `nid_birth_no` | varchar(60) |

### টেবিল: `users` (পরিবর্তন)

| Column | Change |
|--------|--------|
| `student_id` | নতুন — unique FK → students.id (এক student → এক account) |

---

## 2. Registration Flow

```
🧾 STEP 1 — Validate (2 fields only)
   reg_no + phone_no submit
        |
        ├─ students table-এ (reg_no) এবং (phone_no) দুটোই match?
        |     |
        |     ├─ না মিললে → error: "আপনার তথ্য আমাদের রেকর্ডে পাওয়া যায়নি,
        |     |                   কারও সাথে যোগাযোগ করুন" → শেষ
        |     |
        |     └─ মিললে → STEP 2
        |
        └─ চেক: status == registered? → "ইতিমধ্যে account আছে, login করুন"
                 status == blocked? → "আপনার access পুনরায় সক্রিয় করা হয়নি"

📝 STEP 2 — Personal Info Form
   সব গ্রুপ-B field (name, father, mother, address, pro_pic, ssc_roll,
   ssc_registration, whatsapp, dob, gender, blood, nid_birth_no...)
        |
        └─ submit → students record টি update

👤 STEP 3 — Create User
   UserID (email) + Password (2 বার, confirm)
        |
        ├─ users টেবিলে: role=student, status=active, student_id=লিংক
        ├─ সেই email-এ verification mail (ঐচ্ছিক, sprint-8)
        └─ students.status = registered, registered_at = now
             → double registration আর সম্ভব নয়

🎉 শেষে → Auto-login → dashboard
```

---

## 3. Validation Rules

| নিয়ম | বিস্তারিত |
|-------|----------|
| Match both | reg_no এবং phone_no দুটোই exact মিলতে হবে |
| Single use | এক reg_no দিয়ে একবারই — `students.email` unique + `users.student_id` unique উভয়েই রক্ষা করবে |
| Phones | বাংলা ফরম্যাট `+8801XXXXXXXXX` / `01XXXXXXXXX` → registration-এ normalize করে save |
| Email duplicate | অন্য কেউ আগে ব্যবহার করলে ব্লক |
| Password | min 8, confirm-match, bcrypt hash |
| pro_pic | জরুরি নয়, তবে থাকলে 2MB limit, jpg/png/webp |

---

## 4. Edge Cases

- **Wrong combination:** reg_no ঠিক কিন্তু phone ভুল → rejection (কেউ অপরের reg_no দিয়ে fake register করতে না পারে)
- **Already registered:** duplicate attempt → login-এ redirect
- **Blocked:** admin suspend → login বন্ধ, error message
- **প্রি-রেকর্ডে টাইপো:** no match → permit denied; admin contact করতে বলা হবে

---

## 5. Admin Side (Plan-7-তে)

- **Student List management:** add/edit/block/import প্রি-রেকর্ড
- প্রতিটি students record-এ glance: registered? কোন account? কোন batch?

---

## 6. Sprint Mapping

| Sprint | Plan-1 অংশ |
|--------|-----------|
| Sprint 2 (Auth) | Step-1 validate page, Step-2 personal form, Step-3 user creation, login/logout |
| Sprint 3 (Profile) | pro_pic upload, তথ্য edit |
| Sprint 7 (Admin) | pre-seed CRUD + block/unblock |

---

## 7. Current Status

- `students` টেবিল ✅ (২০ কলাম, migration run)
- `users.student_id` ✅
- Test data: ৩ জন pending (EDU-2026-0001 ~ EDU-2026-0003, batch 1)
- Sprint 2 বাকি