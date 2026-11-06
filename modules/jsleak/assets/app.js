/**
 * bafanglab - 第4关前端静态资源（演示文件，AK/SK 均为虚构）
 * 版权所有：八方网域-无涯
 */

// 应用全局配置（真实项目中这类配置绝不应出现在前端）
var APP_CONFIG = {
    env: 'prod',
    apiBase: '/modules/jsleak/api',
    oss: {
        region: 'oss-cn-hangzhou',
        bucket: 'bafanglab-oss-demo',
        // 虚构的阿里云 AccessKey（仅用于靶场演示）
        accessKeyId: 'LTAI5tB8fAnGLaBFakeKey01', // @vuln
        accessKeySecret: 'FakeSecretBafangLab2026AbCdEfGh' // @vuln
    }
};

// 内部接口路由表
var API_ROUTES = {
    userList: APP_CONFIG.apiBase + '/users.php',
    userDetail: APP_CONFIG.apiBase + '/user.php?uid={uid}',
    orderList: APP_CONFIG.apiBase + '/orders.php'
};

// 模拟页面初始化
(function () {
    if (typeof document === 'undefined') { return; }
    var el = document.getElementById('portalStatus');
    if (el) {
        el.innerHTML = '前端资源加载完成，环境：' + APP_CONFIG.env;
    }
})();
