import type { Metadata } from 'next';
import './globals.css';
export const metadata: Metadata = { icons: { icon: '/icon.svg' }, title: 'ENGLISH QUEST · Hành trình tiếng Anh của bạn', description: 'Học tiếng Anh qua những nhiệm vụ nhỏ. Flashcard, ngữ pháp, luyện nghe và kiểm tra dành cho người Việt.' };
export default function Layout({children}: Readonly<{children: React.ReactNode}>) { return <html lang="vi"><body>{children}</body></html>; }
