FROM debian:jessie

ENV DEBIAN_FRONTEND noninteractive

# Install core dependencies
RUN apt-get update \
    && apt-get install -y --no-install-recommends curl ca-certificates nano wget \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/* /usr/share/doc/*

# Install kenrel

RUN apt-get update \
    && apt-get install -y \
        apt-transport-https \
        lsb-release \
        git \
        gcc \
        make \
        re2c \
        libpcre3-dev

RUN wget -O /etc/apt/trusted.gpg.d/php.gpg https://packages.sury.org/php/apt.gpg \
    && echo "deb https://packages.sury.org/php/ $(lsb_release -sc) main" > /etc/apt/sources.list.d/php.list

RUN apt-get update \
    && apt-get install -y \
        php7.0 \
        php7.0-mbstring \
        php7.0-xml \
        php7.0-zip \
        php7.0-dev

RUN apt-get install -y sudo

ADD ./build.sh /root/build.sh
RUN chmod 0777 /root/build.sh

#####################################
# COMPOSER
#####################################

# Install composer and add its bin to the PATH.
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN echo "" >> ~/.bashrc && \
    echo 'export PATH="/root/vendor/bin:$PATH"' >> ~/.bashrc


#####################################
# RUN ZEPHIR
#####################################

WORKDIR /root
RUN git clone --depth=1 -v https://github.com/phalcon/zephir

WORKDIR /root/zephir
RUN ./install -c

WORKDIR /root

CMD ["/root/build.sh"]