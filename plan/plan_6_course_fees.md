# প্ল্যান ৬ — Course Fees & Payment

## উদ্দেশ্য

শিক্ষার্থী **যেই কোর্সে admission আছে তার ফি পেজ** দেখবে — মোট ফি, এ পর্যন্ত কত পেমেন্ট করেছে, কত বাকি, পরিশোধের শেষ তারিখ, পেমেন্ট নম্বর/TRID। **ফি পরিশোধ করলে** TRID + স্ক্রিনশট + সেন্ডার নাম্বার দিয়ে সাবমিট করতে পারবে (admin verify করার পর paid ধরা হবে)।

---

## 1. Scope

- **কোন কোর্স:** শুধু **admission (enrolled)** করা কোর্সের ফি
- **ফি স্টোরেজ:** `courses.fee` (এক কোর্স → এক ফি)
- **পেমেন্ট:** manual proof submission → **admin verify** → তারপর paid-এ যোগ

**অনুমান:** ধরে নিচ্ছি ফি course-ভেদে এক; ব্যাচ-ভেদে দাম ভিন্ন হলে পরে `course_fees` table করা যাবে।

---

## 2. DB Changes

### `courses` — fee
| Column | Change |
|--------|--------|
| `fee` | ➕ decimal(10,2), default 0 |

### `user_courses` — payment deadline
| Column | Change |
|--------|--------|
| `fee_deadline` | ➕ date (null) — পরিশোধের শেষ তারিখ |

### নতুন টেবিল: `payments`
| Column | Type | Constraint |
|--------|------|-----------|
| `id` | bigint | PK |
| `user_id` | bigint | FK → users (cascade) |
| `course_id` | bigint | FK → courses (nullable) |
| `trx_id` | varchar(60) | **UNIQUE** (ডুপ্লিকেট রোধ) |
| `sender_number` | varchar(20) | — |
| `amount` | decimal(10,2) | — |
| `screenshot` | varchar(255) | null (upload path) |
| `note` | text | null |
| `status` | enum | pending / verified / rejected |
| `verified_by` | bigint | FK → users (null) |
| `verified_at` | timestamp | null |
| `created_at` / `updated_at` | timestamp | auto |

---

## 3. Page — `/fees`

### A. Enrolled কোর্স কার্ড (প্রতিটির জন্য)

```
Power by Web Dev  [batch: Batch 1]
├─ মোট ফি:              ৳ 12,000
├─ পেমেন্ট করেছে:        ৳ 8,000  (verified total)
├─ বাকি:                ৳ 4,000  (red জায়ে)
├─ পরিশোধের শেষ তারিখ:   30 Sep 2026
├─ শেষ পেমেন্ট:          ৳ 2,000 · 25 Aug 2026 · TRX123456
└─ [💳 নতুন পেমেন্ট সাবমিট] button
```

### B. Payment History (টেবিল)
| Date | Course | TRID | Sender | Amount | Status |
|------|--------|------|--------|--------|--------|
| 25 Aug | WWD | TRX123456 | 017... | ৳2,000 | ✅ verified |
| 10 Aug | WWD | TRX111 | 017... | ৳6,000 | ✅ verified |
| 05 Sep | WWD | TRX999 | 017... | ৳1,000 | ⏳ pending |

### C. Payment Submit Form (মোডাল/পেজ)
- **Course** (enrolled dropdown)
- **TRID** (required) — সঠিক ফরম্যাট validate, unique
- **Sender Number** (required) — ০১XX... ফরম্যাট
- **Amount** (required)
- **Screenshot** (upload, জরুরি নয় তবে পছন্দনীয়)
- **Note** ঐচ্ছিক
- Submit → status=`pending` → history-তে ⏳ দেখাবে

---

## 4. Paid / Due নিয়ম

| নিয়ম | |
|-------|---|
| **paid** | শুধু `status=verified` payments-এর SUM |
| **due** | `courses.fee − paid` (ঋণাত্মক হলে 0) |
| pending payment | didn't contribute, শুধু badge ⏳ |
| rejected | red badge, বকেয়া আগের মতোই থাকবে |
| full paid | due=0 → card-এ "✅ ফি সম্পন্ন" badge |
| deadline পেরিয়ে due>0 | লাল সতর্কতা |

---

## 5. Upload নিয়ম

| | |
|---|---|
| Path | `public/uploads/payments/{user_id}/` |
| File | jpg · png · jpeg · pdf |
| Size | max 5MB |
| Permanent | verified/rejected দুটোই কীপ হয় (রেকর্ড ধরে রাখা) |

---

## 6. Security / Flow

- **শুধু নিজের** কোর্সের জন্য পেমেন্ট ও দেখাশুনা
- TRID **unique** — একই TRID দ্বিতীয়বার দিলে ব্লক
- **Verify করবে admin** (Sprint 7: pending list → verify/reject + feedback)
- Payment সব user (student) দেখবে, verify শুধু admin/mentor

---

## 7. Sprint Mapping

| Sprint | Plan-6 অংশ |
|--------|-----------|
| Sprint 6 (Fees) | page, history, submit payment |
| Sprint 7 (Admin) | verify/reject payment, fee_deadline সেট |

---

## 8. Current Status

- ✅ Design final
- ⏳ DB: `courses.fee`, `user_courses.fee_deadline`, `payments` migration — Sprint 6

---

## 9. ফাইল

- Migration: `add_fee_to_courses`, `add_fee_deadline_to_user_courses`, `create_payments_table`
- Controller: `FeeController`
- Routes: `GET /fees` · `POST /fees/pay`
- Templates: `fees/index.html.twig`
- Service: `FeesService` (paid/due হিসাব, TRID check)