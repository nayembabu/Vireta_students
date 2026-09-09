# প্ল্যান ২ — Student Profile Setup

## উদ্দেশ্য

Student-দের নিজের সম্পূর্ণ profile নিজেরাই আপডেট করার ক্ষমতা। একমাত্র **`educational_registration_no`** ও **`phone_no`** — এই ২টি লক করা থাকবে (এগুলো student identity key; এডিট/বদলানো যাবে না, শুধু দেখতে পাবে)।

---

## 1. Scope — যা এডিট করা যাবে

**গ্রুপ B — সব students ফিল্ড:**
| Column | Note |
|--------|------|
| `name` | required |
| `father_name` / `mother_name` | required |
| `email` | **নতুন email → mail verify → তারপর login ID বদলাবে** |
| `address` | text |
| `pro_pic` | upload → শুধু students.pro_pic |
| `ssc_roll` / `ssc_registration` | এডিটযোগ্য |
| `whatsapp_number` / `emergency_phone` | optional |
| `date_of_birth` / `gender` / `blood_group` / `nid_birth_no` | — |

**Locked (display-only):**
| Column | কারণ |
|--------|------|
| `educational_registration_no` | student identity key |
| `phone_no` | validation-এ ব্যবহৃত key |

**পাশাপাশি:**
- **Password change** — পুরনো password verify করে নতুন password
- **Profile photo upload** — **শুধু `students.pro_pic`** এ save (`users`-এ কোনো picture কলাম থাকবে না)

---

## 2. DB Changes (নতুন migration)

### টেবিল: `email_verifications` (নতুন)

Email change verify-র জন্য (password_resets-এর মতো):

| Column | Type | Constraint |
|--------|------|-----------|
| `id` | bigint | PK |
| `user_id` | bigint | FK → users (cascade) |
| `new_email` | varchar(190) | — |
| `token` | varchar(64) | UNIQUE |
| `expires_at` | timestamp | ৩০ মিনিট |
| `created_at` | timestamp | — |

**কেন:** email = UserID; কেউ ভুল email দিলে বা অন্যের email নিতে চাইলে যেন ঠেকানো যায় — link click করার পরেই বদলানো হবে।

---

## 3. Profile Page Layout

```
┌─────────────────────────────────────────────┐
│  👤 Profile                                   │
│  [Photo]  name      reg_no (🔒 show-only)     │
│           batch     phone_no (🔒 show-only)   │
│  ─────────────────────────────────           │
│  ব্যক্তিগত: father, mother, dob, gender,      │
│             blood, nid_birth_no              │
│  যোগাযোগ:  email*, whatsapp, emergency_phone  │
│  ঠিকানা:   address                            │
│  শিক্ষা:   ssc_roll, ssc_registration         │
│  ─────────────────────────────────           │
│  [🔑 Password বদলাও]  [📷 ছবি আপলোড]          │
└─────────────────────────────────────────────┘
```

---

## 4. Email Change Flow

```
1. Profile-এ নতুন email submit
   ├─ validate: format + unique (users.email + students.email)
   └─ email_verifications-এ row (token 64 chars, expires 30min)
2. নতুন email-এ verification link পাঠানো হয় (Symfony Mailer)
3. Student link ক্লিক করে
   ├─ token + expires check
   └─ users.email ও students.email একসাথে update
4. Confirm → এখন থেকে নতুন email দিয়ে login
```

> ⚠️ **Dependency:** Mailer কাজ করতে `.env`-এ SMTP setting লাগবে (গ্নিজি/আনডিসিপ্লিন)। সেটা Sprint-2/৩-এ সেটআপ করব; না থাকলে mail সেন্ড হবে local/log-এ।

---

## 5. Photo Upload

| নিয়ম | |
|-------|---|
| Format | jpg / png / webp |
| Size | max 2MB |
| Path | `public/uploads/profiles/{student_id}_{timestamp}.{ext}` |
| Effect | নথি old file delete, শুধু `students.pro_pic` update |

> 🔒 **নিয়ম:** `users` টেবিলে **কোনো picture column নেই** — প্রোফাইল ছবির একমাত্র source of truth = `students.pro_pic`।

---

## 6. Password Change Rules

- পুরনো password **মিলতেই হবে** (Hash::check)
- নতুন password: min 8, confirm-match
- save → bcrypt → session-এ logout করে নতুন password দিয়ে login

---

## 7. Synchronization (দুই টেবিল)

| Field | students | users |
|-------|:--------:|:-----:|
| `name` | ✅ | ✅ |
| `email` | ✅ | ✅ |
| `pro_pic` | ✅ | — (users-এ picture column **না**) |
| বাকি সব | ✅ | — |

---

## 8. Sprint Mapping

| Sprint | Plan-2 অংশ |
|--------|-----------|
| Sprint 2 (Auth) | — (password reset মেইল সেটআপ) |
| Sprint 3 (Profile) | Profile page, edit form, photo upload, password change, email verify |
| Sprint 7 (Admin) | *none* |

---

## 9. Current Status

- ✅ Design finalized (উপরের ৮টা সিদ্ধান্ত)
- ⏳ DB: `email_verifications` migration — Sprint 3-এর শুরুতে
- ⏳ SMTP setting — না থাকলে mail log-এ পড়বে

---

## 10. ফাইল (Sprint 3-তে তৈরি হবে)

- `database/migrations/2026xxxx_create_email_verifications_table.php`
- `templates/profile.html.twig` (view + edit + sections)
- routes: `GET/POST /profile`, `POST /profile/photo`, `POST /profile/password`, `GET /profile/email/verify`