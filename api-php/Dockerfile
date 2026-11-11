FROM php:8.2-cli

# Cài đặt các thư viện cơ bản cần thiết của Linux
RUN apt-get update && apt-get install -y \
    gnupg2 \
    curl \
    apt-transport-https \
    lsb-release \
    unixodbc-dev

# Tải và lưu Microsoft GPG key vào thư mục keyrings
RUN mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /etc/apt/keyrings/microsoft.gpg

# Cố định kho Microsoft về Debian 12 (bookworm)
RUN echo "deb [arch=amd64 signed-by=/etc/apt/keyrings/microsoft.gpg] https://packages.microsoft.com/debian/12/prod bookworm main" > /etc/apt/sources.list.d/mssql-release.list

# Cập nhật lại apt và cài đặt Microsoft ODBC Driver 18
RUN apt-get update && ACCEPT_EULA=Y apt-get install -y msodbcsql18 mssql-tools18

# CỐ ĐỊNH PHIÊN BẢN SQLSRV CHO PHP 8.2 (Dùng bản 5.12.0 thay vì để tự động tải bản PHP 8.3)
RUN pecl install sqlsrv-5.12.0 pdo_sqlsrv-5.12.0 \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Copy code vào container
WORKDIR /app
COPY . .

# Chạy PHP Server
CMD ["php", "-S", "0.0.0.0:3000", "index.php"]