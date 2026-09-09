# প্ল্যান ৪ — Class Routine

## উদ্দেশ্য

Student login করলে এই পেজে **নিজের ব্যাচের ক্লাস রুটিন** দেখতে পাবে — **কোন তারিখে**, **কয়টা থেকে শুরু**, **কয়টায় শেষ**, সাথে topic/room/link/mentor। রুটিন হয় **তারিখ-ভিত্তিক manual** — admin প্রতিটি ক্লাস আলাদা entry দেবে।

---

## 1. Scope

| প্রশ্ন | সিদ্ধান্ত |
|--------|----------|
| রুটিনের ধরন | **তারিখ-ভিত্তিক manual** (admin প্রতি ক্লাস entry দেয়) |
| প্রতি ক্লাসে | **Topic · Meeting link · Room · Mentor** + তারিখ/সময় |
| ডিসপ্লে | **তারিখ অনুযায়ী লিস্ট** — upcoming প্রথমে, past গ্রে-out |

---

## 2. DB — নতুন টেবিল: `routines`

| Column | Type | Constraint |
|--------|------|-----------|
| `id` | bigint | PK |
| `batch_id` | bigint | FK → batches (cascade) |
| `course_id` | bigint | FK → courses (cascade) |
| `mentor_id` | bigint | FK → users (nullable, cascade) |
| `session_date` | date | index |
| `start_time` | time | — |
| `end_time` | time | — |
| `topic` | varchar(190) | nullable |
| `room` | varchar(100) | nullable |
| `meeting_link` | varchar(255) | nullable |
| `created_by` | bigint | FK → users (nullable) |
| `created_at` / `updated_at` | timestamp | auto |

**Index:** `(batch_id, session_date)` — student পেজের query-র জন্য।

> **Batch নির্ধারণ:** student-এর জনমত ব্যাচ = **`users.batch_id`** (account-এর বাংলাদেশ)। `students.batch_id` শুধু প্রি-রেজিস্ট্রেশন রেকর্ড; `users.batch_id`-কেই routine-এর জন্য করতে হবে।

---

## 3. Student Page — `/routine`

```
┌─ ব্যাচ: Batch 1 - Web Dev ────────────────┐
│                                            │
│  🗓️ 15 Sep 2026 (মঙ্গলবার)  — Today      │
│     ├─ 10:00 – 12:00  PHP Basics        │
│     │    Room: Lab-2 · Mentor: X · 🔗    │
│     └─ 02:00 – 04:00  DB Design          │
│                                            │
│  🗓️ 16 Sep 2026 (বুধবার)                │
│     ├─ 11:00 – 01:00  Laravel Intro      │
│     └─ ...                                │
│                                            │
│  🗓️ ... (past)  —  গ্রে-আউট              │
│  [নতুন কিছু নেই — সব ক্লাস এখানে]         │
└────────────────────────────────────────────┘
```

### নিয়ম

- লিস্ট `session_date ASC, start_time ASC` — upcoming প্রথমে
- **Past হলে** → গ্রে-আউট (read-only, দেখায়), **Future/Today** → colorful
- প্রতিটি item: `session_date`, `start/end time` (bold), course title, topic, room, mentor নাম, meeting link (🔗 clickable, new tab)
- ব্যাচ নেই এমন student → message "ব্যাচ নির্ধারিত হয়নি"
- এখান থেকে কোনো action নেই — শুধু view (admin পেজে edit হবে)
- পরবর্তীতে dashboard-এ "আজকের ক্লাস" widget যোগ করা যাবে

---

## 4. Rules / Validation (Admin side — Sprint 7)

| নিয়ম | |
|-------|---|
| Required | batch, course, session_date, start_time, end_time |
| Order | `end_time > start_time` |
| Past entry | historical classes রাখা যায় (গ্রে দেখা) — নতুন entry দেয়া যাবে |
| Overlap | একই ব্যাচে + একই date-এ overlap করা যাবে না (চেক) |
| Meeting link | `https://` → clickable |

---

## 5. Sprint Mapping

| Sprint | Plan-4 অংশ |
|--------|-----------|
| Sprint 5 (Attendance)? | উপস্থিতি-marking এর সাথে session link |
| Sprint 6/7 | Student routine page (Sprint 6) |
| Sprint 7 (Admin) | Routine CRUD (add/edit/delete) + overlap check |

---

## 6. Current Status

- ✅ Design final (৩টি সিদ্ধান্ত + এই ডক)
- ⏳ DB: `routines` migration — Sprint 6 শুরুর migration (সাথে Plan 2, 3-এর pending migrations)

---

## 7. ফাইল

- Migration: `create_routines_table`
- Controller: `RoutineController::index`
- Route: `GET /routine`
- Template: `routine/index.html.twig`
- Query: `routines WHERE batch_id = users.batch_id ORDER BY session_date ASC, start_time ASC`