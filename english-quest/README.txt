ENGLISH QUEST – CHINH PHỤC TIẾNG ANH
MVP giai đoạn 1 · Next.js / React / TypeScript / Tailwind CSS

CHẠY TRÊN MÁY
1. Cài Node.js 24 LTS và pnpm 11.25.0.
2. Mở terminal trong thư mục english-quest.
3. Chạy: pnpm install --frozen-lockfile
4. Chạy: pnpm dev
5. Mở http://localhost:3000
Dev server lắng nghe trên 0.0.0.0 để có thể thử trên điện thoại cùng Wi-Fi
bằng http://<địa-chỉ-IP-máy-tính>:3000 nếu tường lửa cho phép.

KIỂM TRA VÀ BUILD
pnpm check
pnpm build
Kết quả xuất tĩnh ở out/. Dùng một HTTP static server để xem out/.
Không dùng next start cho cấu hình output: export.
Ví dụ: pnpm dlx serve out
Không cần tài khoản Supabase hoặc API key để chạy MVP.
Nếu Next gặp lỗi ghi telemetry trên Windows, đặt NEXT_TELEMETRY_DISABLED=1.

CẤU TRÚC
app/layout.tsx       Tiêu đề trang, SEO cơ bản, ngôn ngữ và favicon
app/page.tsx         Các màn học, điều hướng, chấm điểm và lưu tiến độ
app/globals.css      Giao diện pastel và responsive
app/content.ts       6 chủ đề gốc, hội thoại, ngữ pháp và lời giải
app/expanded.ts      60 flashcard, 120 bài tập và 6 đề x 15 câu
public/quest-team.png Bộ nhân vật Luna, Mimi, Yuki, Mochi tạo riêng bằng ImageGen
public/quest-world.png Bản minh họa ý tưởng ban đầu, hiện không dùng trong giao diện
public/icon.svg      Biểu tượng ngôi sao riêng của ENGLISH QUEST
supabase/schema.sql  Thiết kế DB và RLS cho giai đoạn 2, chưa kết nối
.env.example        Cấu hình mẫu, không có khóa bí mật
.openai/hosting.json Cấu hình xuất bản Sites; không có thông tin đăng nhập
test-results.json   Kết quả kiểm tra tự động gần nhất
ASSETS.txt          Nguồn và mô tả tạo hình

ĐÃ HOÀN THÀNH
- Trang chủ bám bố cục 8 ảnh: header avatar, banner nhóm, 4 thẻ pastel,
  cột bài học và cột tiến độ, XP, chuỗi ngày, vòng hoàn thành.
- 6 màn chính: trang chủ, từ vựng, ngữ pháp, luyện nghe, kiểm tra, thành tích.
- Danh sách có tìm kiếm, chủ đề và trạng thái; dữ liệu hiện chỉ ở A1.
- 60 thẻ (10/chủ đề), IPA, nghĩa Việt, ví dụ Anh–Việt, phát âm, ghi nhớ.
- Ôn thẻ theo lịch 1, 2, 4... tối đa 30 ngày; “Chưa nhớ” đặt ôn hôm nay.
- 120 câu hỏi/bài tập trong ngân hàng, gồm trắc nghiệm, điền từ,
  nhập câu sắp xếp, sửa lỗi và dịch câu. Một số câu được dùng lại trong đề.
- 6 bài nghe, mỗi bài 4 lượt thoại và 1 câu nghe hiểu; ẩn/hiện lời và nghĩa.
- 6 đề kiểm tra, 15 câu/đề chia 3 phần; không giới hạn giờ trong bản A1.
- Chấm ngay, lời giải, xem lại kết quả và làm lại.
- +10 XP/thẻ mới hoặc câu đúng; +50 XP/bài hoàn thành; +1 kim cương/bài.
  Mỗi mục nhận thưởng một lần. Câu sai không bị trừ XP.
- Hạng Trứng, Đồng, Bạc, Vàng, Kim cương, Master; huy hiệu và biểu đồ 7 ngày.
- Lưu tại localStorage, xuất/nhập JSON; không có thành tích giả.
- Thời gian học tính theo khoảng 15 giây khi màn bài học đang hiển thị.
- Responsive desktop/tablet/mobile, focus bàn phím, giảm chuyển động.

GIỚI HẠN VÀ GIAI ĐOẠN 2
- Tiến độ chỉ thuộc trình duyệt/thiết bị hiện tại; chưa có tài khoản hoặc đồng bộ.
- LocalStorage là dữ liệu cá nhân cục bộ, người dùng có thể sửa qua DevTools.
  Vì vậy XP này chưa dùng cho bảng xếp hạng nhiều người hay phần thưởng có giá trị.
- Chưa có luyện nói/gia sư AI, video, xếp hạng nhiều người, cửa hàng trang phục.
- Chưa có nội dung A2–C2, IELTS, TOEIC; không tự nhận chứng nhận CEFR.
- Giọng đọc dùng Web Speech API của thiết bị, phụ thuộc trình duyệt và giọng Anh
  được cài. Chưa có audio thu sẵn, timing đồng bộ hay chấm điểm phát âm.
- Bài nhập câu dùng đáp án chuẩn, bỏ qua hoa/thường và . , ? !;
  chưa có AI đánh giá các cách diễn đạt đúng khác. Đối chiếu lời giải khi cần.
- Minh họa và avatar nhất quán từ một bộ gốc; chưa có bộ biểu cảm/tư thế
  riêng biệt cho toàn bộ bài học.
- Chưa có PWA offline/service worker; website hiện hoạt động trực tuyến.
- CSS animation dùng cho hiệu ứng nhẹ; chưa thêm Motion/Framer Motion.
- Đã kiểm tra kích thước màn hình trong Chrome; chưa kiểm thử trên thiết bị
  iOS/Android thật. Âm thanh cần được nghe kiểm tra trên thiết bị đích.

SUPABASE: CHUẨN BỊ GIAI ĐOẠN 2
1. Tạo dự án Supabase mới, mở SQL Editor và chạy supabase/schema.sql.
2. Bật phương thức đăng nhập thích hợp trong Authentication.
3. Tạo backend Next.js riêng (bỏ output: export khi thêm API/server actions)
   hoặc một API được xác thực độc lập; bản static hiện tại không chạy backend.
4. Đặt URL/publishable key vào biến NEXT_PUBLIC_* tương ứng.
   Secret/service key và khóa mô hình AI chỉ đặt trên server.
5. Tạo profile sau khi đăng ký bằng backend; đọc/ghi dữ liệu theo JWT đã xác minh.
6. Đưa nội dung app/content.ts và app/expanded.ts vào bảng lessons/vocabulary/
   questions; đáp án đúng chỉ nằm ở quest_private.answer_keys.
7. Server tự chấm điểm, ghi XP theo event_key duy nhất và cập nhật tiến độ
   trong một transaction. Không nhận điểm hay user_id tùy ý từ frontend.
8. Kiểm thử RLS bằng hai tài khoản khác nhau trước khi đưa lên môi trường thật.
9. Nếu thêm audio tải lên, tạo Storage bucket riêng và policy tương ứng;
   schema hiện chưa tạo bucket hoặc quyền tải file.
Schema là phần chuẩn bị; chưa được chạy/kiểm chứng trên Supabase của bạn.

QUYỀN RIÊNG TƯ
MVP không yêu cầu tên thật, ngày sinh, email hay quyền micro và không gửi
tiến độ tới server ứng dụng. Speech API có thể dùng dịch vụ giọng nói của
hệ điều hành/trình duyệt. Trước khi mở tài khoản trẻ em ở giai đoạn 2 cần
thiết kế đồng ý của người giám hộ, thời hạn lưu, xuất/xóa dữ liệu và nhật ký truy cập.

THIẾT KẾ
Ảnh 1/8: trang chủ, hero 2 phần và 4 thẻ. Ảnh 2: bài học + tiến độ 2 cột.
Ảnh 3: danh sách bài nghe, banner tím, lọc/chủ đề. Ảnh 4: danh sách đề.
Ảnh 5: thống kê và các hạng. Ảnh 6: flashcard hình trái, nội dung phải.
Ảnh 7: bài nghe, trình phát trái và lời thoại phải.
Giữ nhịp bố cục, bảng màu và phong cách; không đưa ảnh tham chiếu vào tài sản sản phẩm.

TRIỂN KHAI
Đã cấu hình xuất tĩnh để hosting có thể phục vụ trực tiếp thư mục out/.
Site mới được xuất bản ở chế độ riêng tư theo mặc định của Sites.
Mỗi lần sửa cần build lại và xuất bản phiên bản mới.

LỘ TRÌNH CẤP ĐỘ
- Danh sách bài học được trình bày thành đường học dọc kiểu Duolingo, với nút tròn so le và trạng thái đã hoàn thành/bài tiếp theo/sắp tới.
- Giữ nguyên nội dung bài, asset và hành vi mở bài; đường học responsive cho desktop và điện thoại.


LỘ TRÌNH CẤP ĐỘ
Danh sách bài học được đổi thành đường học dọc uốn lượn, với nút bài tròn so le, màu trạng thái đã hoàn thành/bài tiếp theo/bài sắp học và nhãn XP. Giữ nội dung bài học, asset, dữ liệu tiến độ và chức năng mở bài như cũ. Responsive cho desktop/điện thoại.

BẢN NÀY LÀ SOURCE
Đây là mã nguồn Next.js của English Quest cũ. Chạy pnpm install --frozen-lockfile, sau đó pnpm build; thư mục xuất tĩnh là out/. Dự án MVP này không dùng private/config.php hoặc thông tin đăng nhập backend.


CHẾ ĐỘ HỌC CÓ LỘ TRÌNH
Trong màn học có thanh hiển thị chủ đề A1 và bốn chặng Từ vựng → Ngữ pháp → Luyện nghe → Kiểm tra. Mỗi chặng mở đúng chế độ hiện có cho cùng chủ đề; trạng thái hoàn thành lấy từ tiến độ hiện tại. Thanh co giãn trên điện thoại.

