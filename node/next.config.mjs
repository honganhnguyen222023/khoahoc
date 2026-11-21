/** @type {import('next').NextConfig} */
const nextConfig = {
  reactStrictMode: true,
  poweredByHeader: false,
  images: { unoptimized: true },
  // Đường dẫn giống bản WordPress (có dấu / ở cuối).
  trailingSlash: true,
};
export default nextConfig;
