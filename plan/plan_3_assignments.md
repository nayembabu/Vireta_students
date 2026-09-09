# প্ল্যান ৩ — Assignments

## উদ্দেশ্য

Assignments পেজে **enrolled course-গুলোর** সব assignment-এর একটি লিস্ট। প্রতিটি অ্যাসাইনমেন্টের অবস্থা:

- ✅ যেটা **সাবমিট করা হয়েছে** → দেখা যাবে (সবুজ ✓)
- 🔴 যেটা সাবমিট করা **আছে না** → **লাল** হাইলাইট (যদি due পেরিয়ে গেছে = missed; এখনো বাকি থাকলে submit-এর সুযোগ)
- 🕐 **due_date পার হয়নি** → সাবমিট করা যাবে **এবং এডিট করা যাবে**
- ⛔ **due_date পার হয়ে গেছে** → সাবমিট বা এডিট **আর করা যাবে না** (শুধু পড়া/দেখা)

---

## 1. Scope

| প্রশ্ন | সিদ্ধান্ত |
|--------|----------|
| কার assignment? | **শুধু enrolled course-এর** assignment লিস্টে আসবে |
| উত্তর কীভাবে? | **text + file** (দুটোই; অন্তত একটা required) |
| Edit আচরণ | **Version history** — প্রতি edit-এ নতুন version, পুরনো submission থেকে যায় |

---

## 2. Pages

### A. `/assignments` — List (এক পেজে সব enrolled course)

প্রতি assignment-এর জন্য:

| State | Display | Action |
|-------|---------|--------|
| 🔴 **Missed** | সাবমিট নেই + `now > due_date` | লাল কার্ড, "মেয়াদ শেষ" label | কার্যক্রম নেই (read-only) |
| 🟡 **Open** | সাবমিট নেই + `now <= due` | সাদা কার্ড, countdown | **[Submit]** button |
| 🟢 **Submitted** | সাবমিট আছে + `now <= due` | সবুজ badge ✓, version count | **[Edit]** button (resubmit) |
| 🔵 **Graded** | সাবমিট আছে + `now > due` | স্কোর/ফিডব্যাক দেখায় | View details |

Icon/badge: লাল `Missed` → whole card red-tinted।

### B. `/assignments/{id}` — Detail + Submission

```
Assignment info: title, description, course, due_date, max_score
  ├─ (যদি এখনও খোলা) Submission form:
  │      textarea (ঐচ্ছিক) + file upload (ঐচ্ছিক)  [কমপক্ষে একটা]
  │      file: pdf/doc/docx/zip/jpg/png, max 10MB
  │      অনুমোদন শুধু if now <= due_date (server-সাইড check)
  ├─ My submissions (version history):
  │      v3 (latest)  — submitted_at, text, file
  │      v2           — ...
  │      v1           — ...
  └─ Grading (যদি থাকে): score / max_score, feedback, graded_by
```

### C. `POST /assignments/{id}/submit`

- এখনো due না হলে: নতুন **version** তৈরি (submit = v1, edit = v+1)
- ফাইল হলে: `uploads/assignments/{assignment_id}/{user_id}/v{version}.{ext}` — পুরনো version-এর ফাইল **কীপ করা হবে**
- সেভ-এর পর `/assignments/{id}` → এ redirect

---

## 3. DB Changes (Sprint 6-তে)

### `assignment_submissions` — version support

| Change | |
|--------|---|
| ❌ drop unique (`assignment_id`, `user_id`) | — |
| ➕ add `version` int unsigned default 1 | — |
| ➕ add unique (`assignment_id`, `user_id`, `version`) | ডুপ্লিকেট রোধ |

- **Latest submission** = `WHERE assignment_id=? AND user_id=? ORDER BY version DESC LIMIT 1`
- Grading (Sprint 7) latest version-এ হবে
- `status` enum: submitted / graded / returned — রাখা আছে
  - `returned` = শিক্ষক পুনরায় জমা দিতে বলেছে → due-এর আগে **নতুন version** দিয়ে resubmit সম্ভব

---

## 4. Rules / Validation

| নিয়ম | বিস্তারিত |
|-------|----------|
| Deadline | `now <= due_date` হলেই submit/edit; server-সাইড enforce |
| Content | text বা file — **কমপক্ষে একটা** |
| File | pdf, doc, docx, zip, jpg, png · max **10MB** |
| Version | প্রতি edit → version+1, পুরনো থেকে যায় (history) |
| Edit | শুধু latest version-key; due পার হলে আটকে যাবে |
| Security | শুধু নিজের submission edit/দেখা; অন্য user-এর submission block |

---

## 5. Sprint Mapping

| Sprint | Plan-3 অংশ |
|--------|-----------|
| Sprint 6 (Assignments) | list, detail, submit/edit, version history, deadline gate |

---

## 6. Current Status

- ✅ Design final (৩টি সিদ্ধান্ত + এই ডক)
- ⏳ Implementation — Sprint 6
- ⏳ DB изменение (`version`, unique পরিবর্তন) — Sprint 6 শুরুর migration

---

## 7. ফাইল (Sprint 6-তে)

- Migration: `assignment_submissions` version column
- `AssignmentService` (deadline/sastho check, version নিয়ম)
- routes: `GET /assignments` · `GET /assignments/{id}` · `POST /assignments/{id}/submit`
- templates: `assignments/index.html.twig`, `assignments/show.html.twig`