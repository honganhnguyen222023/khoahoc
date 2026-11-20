export default function NotFound() {
  return (
    <div className="kh-container kh-page kh-404">
      <p className="kh-404-code">404</p>
      <h1 className="kh-h2">Không tìm thấy trang này</h1>
      <p className="kh-lead">Đường dẫn có thể đã đổi. Quay về trang chủ hoặc xem các khóa học.</p>
      <div className="kh-404-actions">
        <a className="kh-btn kh-btn-primary" href="/">Về trang chủ</a>
        <a className="kh-btn kh-btn-outline" href="/khoa-hoc/">Xem khóa học</a>
      </div>
    </div>
  );
}
