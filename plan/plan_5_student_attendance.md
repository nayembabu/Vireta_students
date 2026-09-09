# প্ল্যান ৫ — Student Attendance

## উদ্দেশ্য

শিক্ষার্থী নিজের **হাজিরার সম্পূর্ণ picture** দেখবে — কোন ক্লাসে (কোন তারিখে) এসেছে, **কয়টায় এসেছে**, status কী। সাথে সামারি কার্ড আর আসন্ন ক্লাসের ওভারভিউ।

---

## 1. Scope

| প্রশ্ন | সিদ্ধান্ত |
|--------|----------|
| হাজিরা কীভাবে রেকর্ড | **Admin marking + auto check-in** (কখন এসেছে auto record) |
| পেজে কী | **Past records + সামারি কার্ড + Upcoming ওভারভিউ** |
| রুটিন লিংক | **হ্যাঁ — `attendance.routine_id`**, class start-এর সাথে তুলনা করে present/late |

---

## 2. DB Changes

### `attendance` — routine link

| Column | Change |
|--------|--------|
| `routine_id` | ➕ nullable FK → routines (cascade) |
| `course_id` | রুটিন থেকে ডিনর্মালাইজ করা (রয়ে যায়) |
| `status` | enum: present / absent / late / excused (আগের মতো) |

**Present/Late auto decision** (auto check-in-এ):

```
check_in_time <= routine.start_time + 15min  → present
check_in_time >  routine.start_time + 15min  → late
কোনো check-in নেই, admin "absent" চিহ্নিত      → absent
```

---

## 3. Check-in Flow (কখন এসেছে কীভাবে আসে)

1. ক্লাস চলাকালীন (রুটিনের সময়সীমা) student routine পেজ/ক্লাস পেজ খুললে → **auto check-in**:
   - `attendance` row তৈরি: `routine_id`, `user_id=student`, `session_date=routine.date`, `check_in_time=now`
   - `status` = উপরের নিয়মে present/late
2. Admin/mentor পরে **নিশ্চিত/ওভাররাইড** করে: present/late তো আছেই; অনুপস্থিত → absent, অনুমতিসহ → excused
3. admin ম্যানুয়ালি হাজিরা দিলেও check_in_time সেট করা যাবে

---

## 4. Student Page — `/attendance`

### A. সামারি কার্ড (সব কোর্স মিলিয়ে)
| Card | |
|------|---|
| ✅ Present | মোট টানা উপস্থিত |
| ⏰ Late | দেরি-করা ক্লাস |
| ❌ Absent | অনুপস্থিত |
| 📝 Excused | অনুমোদিত ছুটি |
| 📊 Percentage | `(present + late) / মোট ক্লাস × 100` |

### B. Past Records (course-wise গ্রুপ)

```
PHP Basics (Course)
  📅 15 Sep 2026 · 10:00–12:00  → ✅ Present · এসেছেন 10:05
  📅 12 Sep 2026 · 10:00–12:00  → ⏰ Late  · এসেছেন 10:22
  📅 10 Sep 2026 · 10:00–12:00  → ❌ Absent
DB Design
  ...
```

`session_date` DESC (নতুন আগে) — time + check_in_time + status badge।

### C. Upcoming Overview
- ব্যাচের আসন্ন routine sessions (সব course)
- প্রতিটায়: date/time, "আগামী ক্লাস" লেবেল — হাজিরা এখনো হয়নি (যাবে)

---

## 5. Rules/গুরুত্বপূর্ণ

| নিয়ম | |
|-------|---|
| Read-only | student শুধু দেখবে; change admin-এর (Sprint 7) |
| reverse | এক student-এর routine ↔ এক check-in (routine+user unique) |
| Check-in window | routine.start_time – routine.end_time এর মধ্যে; বাইরে হলে capture হয় না |
| Percentage rule | excused কে না হয় present-এ count (`মোট ক্লাস` থেকে বাদ) — এটা অ্যাডমিন নীতি, পরে চেঞ্জযোগ্য |
| Security | শুধু নিজের record |

---

## 6. Sprint Mapping

| Sprint | Plan-5 অংশ |
|--------|-----------|
| Sprint 5 (Attendance) | check-in capture, page (summary + records + upcoming) |
| Sprint 7 (Admin) | manual marking, override |
| Sprint 6 (Routine) | routine পেজ থেকে check-in trigger |

---

## 7. Current Status

- ✅ Design final
- ⏳ DB: `attendance.routine_id` migration — Sprint 5
- ⏳ implementation Sprint 5

---

## 8. ফাইল

- Migration: `add_routine_id_to_attendance`
- Controller: `AttendanceController::index`
- Route: `GET /attendance`
- Service: `AttendanceService` (check-in capture, present/late logic)
- Template: `attendance/index.html.twig`