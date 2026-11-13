-- ENGLISH QUEST: thiết kế cơ sở dữ liệu cho giai đoạn 2.
-- Chưa được áp dụng lên dịch vụ thật. MVP hiện dùng localStorage.
-- Chạy trong SQL Editor của một dự án Supabase mới.
begin;
create extension if not exists pgcrypto;
create schema if not exists quest_private;
revoke all on schema quest_private from public, anon, authenticated;

-- Users được quản lý bởi Supabase Auth trong auth.users.
create table public.profiles (
  id uuid primary key references auth.users(id) on delete cascade,
  display_name text not null default 'Nhà thám hiểm' check(char_length(display_name) between 1 and 60),
  avatar text not null default 'mochi' check(avatar in ('luna','mimi','yuki','mochi')),
  created_at timestamptz not null default now()
);
create table public.courses (
  id uuid primary key default gen_random_uuid(),
  title text not null,
  cefr text not null check(cefr in ('A1','A2','B1','B2','C1','C2')),
  published boolean not null default false
);
create table public.lessons (
  id uuid primary key default gen_random_uuid(),
  course_id uuid not null references public.courses(id) on delete cascade,
  slug text not null unique, title text not null,
  skill text not null check(skill in ('vocabulary','grammar','listening','test','speaking')),
  content jsonb not null default '{}', published boolean not null default false
);
create table public.vocabulary (
  id uuid primary key default gen_random_uuid(), lesson_id uuid not null references public.lessons(id) on delete cascade,
  english text not null, vietnamese text not null, ipa text, example text, translation text, audio_path text
);
create table public.grammar_exercises (
  id uuid primary key default gen_random_uuid(), lesson_id uuid not null references public.lessons(id) on delete cascade,
  rule_vi text not null, example_en text not null, example_vi text not null
);
create table public.listening_dialogues (
  id uuid primary key default gen_random_uuid(), lesson_id uuid not null references public.lessons(id) on delete cascade,
  lines jsonb not null, audio_path text, timing jsonb
);
create table public.questions (
  id uuid primary key default gen_random_uuid(), lesson_id uuid not null references public.lessons(id) on delete cascade,
  kind text not null check(kind in ('choice','fill','reorder','correct','translate')),
  prompt text not null, options jsonb, position integer not null default 0
);
-- Đáp án không nằm trong bảng mà frontend có thể truy vấn.
create table quest_private.answer_keys (
  question_id uuid primary key references public.questions(id) on delete cascade,
  accepted_answers jsonb not null, explanation_vi text not null
);
create table public.answers (
  id uuid primary key default gen_random_uuid(), user_id uuid not null references auth.users(id) on delete cascade,
  question_id uuid not null references public.questions(id), attempt_id uuid not null,
  submitted text not null, correct boolean not null,
  created_at timestamptz not null default now(), unique(user_id,attempt_id,question_id)
);
create table public.learning_progress (
  user_id uuid not null references auth.users(id) on delete cascade,
  lesson_id uuid not null references public.lessons(id) on delete cascade,
  completed boolean not null default false, best_score integer not null default 0 check(best_score>=0),
  seconds integer not null default 0 check(seconds>=0), updated_at timestamptz not null default now(),
  primary key(user_id,lesson_id)
);
create table public.card_reviews (
  user_id uuid not null references auth.users(id) on delete cascade,
  vocabulary_id uuid not null references public.vocabulary(id) on delete cascade,
  interval_days integer not null default 0 check(interval_days between 0 and 365),
  due_at timestamptz not null default now(), primary key(user_id,vocabulary_id)
);
create table public.xp_transactions (
  id uuid primary key default gen_random_uuid(), user_id uuid not null references auth.users(id) on delete cascade,
  event_key text not null, xp integer not null check(xp>=0), diamonds integer not null default 0 check(diamonds>=0),
  created_at timestamptz not null default now(), unique(user_id,event_key)
);
create table public.achievements (
  id text primary key, title text not null, description text not null, rule jsonb not null
);
create table public.user_achievements (
  user_id uuid not null references auth.users(id) on delete cascade,
  achievement_id text not null references public.achievements(id), earned_at timestamptz not null default now(),
  primary key(user_id,achievement_id)
);
create table public.daily_streaks (
  user_id uuid not null references auth.users(id) on delete cascade,
  activity_date date not null, timezone text not null default 'Asia/Ho_Chi_Minh',
  primary key(user_id,activity_date)
);
create table public.ai_conversations (
  id uuid primary key default gen_random_uuid(), user_id uuid not null references auth.users(id) on delete cascade,
  topic text not null, messages jsonb not null default '[]', created_at timestamptz not null default now()
);

-- Mọi bảng đều bật RLS. Mặc định không có quyền ghi từ trình duyệt.
do $$ declare t text; begin
  foreach t in array array['profiles','courses','lessons','vocabulary','grammar_exercises','listening_dialogues','questions','answers','learning_progress','card_reviews','xp_transactions','achievements','user_achievements','daily_streaks','ai_conversations'] loop
    execute format('alter table public.%I enable row level security',t);
    execute format('revoke all on public.%I from anon, authenticated',t);
    execute format('grant select on public.%I to authenticated',t);
    execute format('grant all on public.%I to service_role',t);
  end loop;
end $$;
create policy profile_owner on public.profiles for select to authenticated using(id=(select auth.uid()));
create policy profile_edit on public.profiles for update to authenticated using(id=(select auth.uid())) with check(id=(select auth.uid()));
grant update(display_name,avatar) on public.profiles to authenticated;
create policy courses_read on public.courses for select to authenticated using(published);
create policy lessons_read on public.lessons for select to authenticated using(published and exists(select 1 from public.courses c where c.id=course_id and c.published));
do $$ declare t text; begin
  foreach t in array array['vocabulary','grammar_exercises','listening_dialogues','questions'] loop
    execute format('create policy lesson_content_read on public.%I for select to authenticated using(exists(select 1 from public.lessons l where l.id=lesson_id and l.published))',t);
  end loop;
  foreach t in array array['answers','learning_progress','card_reviews','xp_transactions','user_achievements','daily_streaks','ai_conversations'] loop
    execute format('create policy owner_read on public.%I for select to authenticated using(user_id=(select auth.uid()))',t);
  end loop;
end $$;
create policy achievements_read on public.achievements for select to authenticated using(true);
grant usage on schema quest_private to service_role;
grant all on quest_private.answer_keys to service_role;

create index answers_user_time on public.answers(user_id,created_at desc);
create index xp_user_time on public.xp_transactions(user_id,created_at desc);
create index reviews_due on public.card_reviews(user_id,due_at);
commit;

-- Backend giai đoạn 2 phải xác minh JWT, tự chấm đáp án và tự tạo event_key;
-- không chấp nhận user_id, XP hoặc correct do client tự khai.
-- Dùng transaction/RPC phía server để ghi đáp án, tiến độ, XP cùng một lần.
-- UNIQUE(user_id,event_key) ngăn cộng XP hai lần cho cùng nhiệm vụ.
-- Chưa có endpoint ghi dữ liệu trong MVP. Không bật client INSERT/UPDATE XP.
