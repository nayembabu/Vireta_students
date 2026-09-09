# প্ল্যান ৭ — Student Dashboard

## উদ্দেশ্য

Login-এর পরের **main landing page** — পুরো পোর্টালের সবকিছুর এক দৃষ্টিতে ওভারভিউ (Plan 3-6-এর সামারি)। প্রতিটি ব্লক থেকে respective detail page-এ যাওয়া যাবে।

---

## 1. Landing

- Login/Register-এর পর `GET /` → **Dashboard** (student)
- Navbar-এ Dashboard-কে home button

---

## 2. Dashboard Layout — Down থেকে Hands

```
┌─ 👋 Greeting Banner ───────────────────────────┐
│  [🖼️ ছবি]  আসসালামু আলাইকুম, রাকিব! 🎉        │
│  Admin           Batch 1 - Web Dev             │
│  ─────────────────────────────────              │
│  📘 2 কোর্স  📊 78% attendance  🔖 3 pending  │
└──────────────────────────────────────────────┘
│                                                 │
│  ┌──4 Stat Cards──────────────┐                 │
│  │ Attendance  78%            │                 │
│  │ Due Fee     ৳4,000 ⚠️      │                 │
│  │ Pending Assignments  2     │                 │
│  │ Unread Notifications  3    │                 │
│  └────────────────────────────┘                 │
│                                                 │
│  ┌── 📅 Class Overview ────────┐ ┌── 🔖 Assignments ┐ │
│  │ আজ 10:00-12:00 PHP Basics   │ │ pending 1 (open)  │
│  │ std:: tomorrow 09:30 DB     │ │ missed 1 (🔴)     │
│  │ due-fed ... (৪ দিন)         │ │ graded 2 (✓)      │
│  └─────────────────────────────┘ └───────────────────┘
│                                                 │
│  ┌── 📚 Course Progress ───────┐ ┌── 💳 Fee Status ┐ │
│  │ WWD   62%  ████████░░        │ │ paid   ৳8,000   │
│  │ DB      30%  ████░░░░░░      │ │ due    ৳4,000   │
│  │ [Continue] button           │ │ deadline 30 Sep ⚠️ │
│  └─────────────────────────────┘ └───────────────────┘
│                                                 │
│  ┌── 🔔 Notifications ─────────┐                │
│  │ • নতুন assignment পোস্ট হয়েছে (2h)  [✓]  │
│  │ • ফি deadline এগিয়ে আছে (1d)         [✓]  │
│  │ • assignment graded (3d)                   │
│  └─────────────────────────────┘                │
```

---

## 3. Block Details

### A. Greeting Banner
- ছবি (`students.pro_pic`), নাম, role, batch নাম, date
- Quick chips: কোর্স count, attendance %, pending assignments

### B. Stat Cards (৪টি)
| Card | উৎস |
|------|-----|
| Attendance % | Plan-5: present+late / মোট × 100 |
| Due Fee | Plan-6: fee − paid (⚠️ যদি deadline কাছাকাছি/পেরিয়ে) |
| Pending Assignments | Plan-3: not submitted + due এখনো পার হয়নি |
| Unread Notifications | `notifications.is_read=0` count |

ক্লিক করলে respective page-এ নিয়ে যাবে।

### C. Class Overview
- **আজকের ক্লাস** (routine) — হাইলাইট; সময়, course
- পরের **৪ দিন** পর্যন্ত routine items
- Past হলে গাঢ়/ম্লান
- [সম্পূর্ণ routine] link → Plan-4 page

### D. Assignments
- সংক্ষিপ্ত: **pending** (open), **missed** (লাল count + label), **graded** (টিক ✓)
- due ডেডলাইন close হলে countdown
- [Assignments] link → Plan-3 page

### E. Course Progress
- প্রতি enrolled কোর্সে: **প্রগ্রেস %** বার (lesson_progress থেকে)
- [Continue learning] → প্রথম unfinished lesson
- এখানেই modules/lessons chapter-ওয়ার

### F. Fee Status
- paid ৳X / due ৳Y per course (প্রথম ২-৩ কোর্স)
- deadline পেরিয়ে + due>0 → লাল সতর্কতা
- [Fees] link → Plan-6 page

### G. Notifications
- Latest **৫ টি**, unread-এ রঙিন dot/bold
- Click → read + link to detail
- [সব] → notifications page (Plan-8)

---

## 4. Data / Query (এক Request-এ)

| ব্লক | টেবিল |
|------|-------|
| Banner/Stat | users(student), students(photo), user_courses, attendance, assignments + submissions, notifications, payments |
| Classes | routines (batch) |
| Assignments | assignments + submission |
| Progress | lesson_progress -> lessons/modules/course |
| Fee | payments (verified) + courses.fee |
| Notice | notifications |

- **Optimization:** সবগুলো এক service (`DashboardService`) — approx subqueries/counts, প্রায় ১ query / block (lazy optional)

---

## 5. Roles

| Role | Dashboard |
|------|-----------|
| student | এই Plan-7 |
| admin/mentor | আলাদা admin dashboard — Sprint 7 (এক নজরে সব students: totals, pending 진행, verify queue) |

---

## 6. Sprint Mapping

| Sprint | Plan-7 অংশ |
|--------|-----------|
| Sprint 3 | Dashboard base + greeting + stat cards |
| Sprint 4 | course progress block |
| Sprint 5 | attendance block → তখন % আসবে |
| Sprint 6 | assignments, fees block + notification block |
| Sprint 8 | notifications polish |

> Dashboard প্রতিটি sprint-এ step-by-step বাড়বে।

---

## 7. Current Status

- ✅ Design finalized (ব্লক + landing)
- ⏳ Implementation sprint-গুলোতে

## 8. ফাইল

- `DashboardController::index` (route `GET /`)
- `DashboardService`
- `templates/dashboard/index.html.twig`
- Partial snippet blocks (dashboard → detail pages)