FROM php:8.2-cli

# Cài đặt các thư viện cơ bản cần thiết của Linux
RUN apt-get update && apt-get install -y \
    gnupg2 \
    curl \
    apt-transport-https \
    lsb-release \
    unixodbc-dev

# Tải và lưu Microsoft GPG key vào thư mục keyrings (Thay thế lệnh apt-key cũ)
RUN mkdir -p /etc/apt/keyrings \
    && curl -fsSL https://packages.microsoft.com/keys/microsoft.asc | gpg --dearmor -o /etc/apt/keyrings/microsoft.gpg

# Thêm repository Microsoft phù hợp với phiên bản Debian hiện tại
RUN echo "deb [arch=amd64 signed-by=/etc/apt/keyrings/microsoft.gpg] https://packages.microsoft.com/debian/$(lsb_release -rs | cut -d. -f1)/prod $(lsb_release -cs) main" > /etc/apt/sources.list.d/mssql-release.list

# Cập nhật lại apt và cài đặt Microsoft ODBC Driver 18
RUN apt-get update && ACCEPT_EULA=Y apt-get install -y msodbcsql18 mssql-tools18

# Cài đặt PHP extensions cho SQL Server (phần này rất quan trọng để PHP kết nối được CSDL)
RUN pecl install sqlsrv pdo_sqlsrv \
    && docker-php-ext-enable sqlsrv pdo_sqlsrv

# Copy code vào container
WORKDIR /app
COPY . .

# Chạy PHP Server
CMD ["php", "-S", "0.0.0.0:3000", "index.php"]