# English Quest AI Chat — cài đặt và đưa lên Hostinger

## Chatbot đã tích hợp

Nút **Con AI · Hỏi mình nhé** xuất hiện trên toàn bộ khu vực đã đăng nhập (Học viện và English Quest Hub). Chatbot dùng API trợ lý Gemini phía máy chủ hiện có, giữ khóa API trong `private/config.php`/biến môi trường, dùng session và CSRF của tài khoản. Nó trả lời câu hỏi học tập, hướng dẫn tính năng/game, có câu hỏi nhanh, ngôn ngữ Việt/Anh, mức hướng dẫn, đọc câu trả lời và lịch sử trợ lý trong Cài đặt. Chatbot không được quyền sửa điểm, tiền tệ hoặc tiến trình.

## Cập nhật mã nguồn hiện tại

1. Dùng đúng source đã tích hợp trong thư mục `source/`.
2. Cài dependencies theo lockfile: `pnpm install --frozen-lockfile`.
3. Kiểm tra kiểu và tạo static export: `pnpm check`, sau đó `pnpm build`.
4. Đưa nội dung build trong `source/out/` lên `public_html/` theo quy trình triển khai hiện có.
5. Đồng bộ các endpoint PHP trong `private/` vào thư mục riêng ngoài `public_html`. Giữ `private/config.php` bên ngoài thư mục public và giữ nguyên cấu hình máy chủ đang chạy. Không đưa secret vào `.env` phía trình duyệt hay file JavaScript.
6. Kiểm tra đăng nhập trước; mở một trang đã đăng nhập; bấm nút chatbot ở góc phải dưới; gửi câu hỏi. Nếu Gemini chưa cấu hình, giao diện hiện thông báo/hướng dẫn dự phòng.

## Khác biệt với repository trong ảnh

Repository `https://github.com/mayooear/ai-pdf-chatbot-langchain` đã được tải về để kiểm tra ở `outputs/ai-pdf-chatbot-langchain-reference/`. README của dự án nói rõ dự án không còn được duy trì. Nó là một ứng dụng mẫu riêng gồm Next.js + dịch vụ LangGraph/Node, cần Supabase (vector store/table/function) và khóa LLM. Hostinger PHP hiện tại không thể chạy toàn bộ dịch vụ đó như một tệp tĩnh; cũng chưa có thông tin Supabase/LLM riêng được cấu hình. Vì vậy website hiện dùng chatbot Gemini đã có trong English Quest, không cài các dependency hoặc triển khai một dịch vụ phụ thuộc bên ngoài chưa cấu hình.

## Giới hạn hiện tại

Chatbot trên web hoạt động cho hỏi đáp học tập/game theo ngữ cảnh người dùng; chức năng tải PDF rồi hỏi trên nội dung PDF của repo mẫu chưa được nối vào. Để bật hỏi đáp tài liệu cần chọn nơi lưu tài liệu/vector (ví dụ Supabase), cách xác thực và giới hạn quyền truy cập tệp. Không được để service role key hoặc Gemini key vào frontend.
