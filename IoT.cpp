#include <string>
#include <iostream>
#include <regex>
#include <sstream>
#include <vector>
#include <map>
#include <fstream>
#include <iomanip>
#include <thread>
#include <chrono>
#include <random>
#include <stdexcept>
#include <utility>
#include <typeinfo>
#include <atomic>
#include <memory>

#include <cstdio>
#include <cstring>
#include <cctype>
#include <cstdint>
#include <cstddef>
#include <csignal>

extern "C" {

#include <af_ieee802154_cp.h>

#include <sys/select.h>
#include <sys/stat.h>
#include <sys/resource.h>
#include <sys/ioctl.h>
#include <sys/types.h>
#include <sys/file.h>

#include <fcntl.h>
#include <mqueue.h>
#include <unistd.h>

#include <time.h>
#include <errno.h>

#include <net/if.h>
#include <net/if_arp.h>

#include <netinet/in.h>

#include <openssl/ssl.h>
#include <openssl/err.h>
#include <openssl/evp.h>
#include <openssl/pem.h>
#include <openssl/x509.h>
#include <openssl/opensslv.h>

#ifdef SIGNAL
#include <signal/signal_protocol.h>
#include <signal/protocol.h>

#include "test/common.h"
#endif

#ifndef __linux__
#error "currently only linux is supported"
#endif

#if defined(__GLIBCXX__) || defined(__GLIBCPP__)
#include <cxxabi.h>
#endif

#if OPENSSL_VERSION_NUMBER < 0x010101000
#error "currently only OpenSSL >= 1.1.1 is supported"
#endif

#include <libpq-fe.h>

#include <postgresql/server/postgres.h>
#include <postgresql/server/fmgr.h>
#include <postgresql/server/pg_config.h>
#include <postgresql/server/catalog/pg_type_d.h>

#if PG_VERSION_NUM < 120000
#error "currently only PostgreSQL >= 12.0 is supported"
#endif

#include <bluetooth/bluetooth.h>
#include <bluetooth/hci.h>
#include <bluetooth/hci_lib.h>

}

using namespace std;

/* commands needed for creating private key:
openssl genrsa -out privateKey.pem 4096 */

/* compile with "-lpq -lbluetooth -lrt -lpthread -lcrypto -lssl -lsignal-protocol-c" */

/* commands needed for creating certificate:
openssl req -new -key privateKey.pem -out csr.pem
echo -e "\n\n\n\n\n\n\n\n" | \
		openssl x509 -in csr.pem -out certificate.pem -req -signkey privateKey.pem -days 365
rm csr.pem */

#ifdef PG_MODULE_MAGIC
extern "C" { PG_MODULE_MAGIC; }
#endif

#define THR(cond, mess) if (cond) throw mess

#define TIMES8(seq) seq seq seq seq seq seq seq seq

#define HEX_NOSHOWB(field, precision) hex << setfill('0') << setw(precision) << (field) << \
		setfill(' ') << dec

#define HEX(field, precision) "0x" << hex << setfill('0') << setw(precision) << (field) << \
		setfill(' ') << dec

#define HEX_INP(field) hex >> (field) >> dec

#define BOOLALPHA_UPPERCASE(field) ((field) ? "TRUE" : "FALSE")

#ifndef NDEBUG
#define LOG_CPP(par) cout << par
#define LOG_CP(fun, ...) fun(cout, __VA_ARGS__)
#define LOG_C(fun, ...) fun(stdout, __VA_ARGS__)
#else
#define LOG_CPP(par) ((void)0)
#define LOG_CP(fun, ...) ((void)0)
#define LOG_C(fun, ...) ((void)0)
#endif

#define PL_MAX 65535

#define msg_MAX 65559

/*
 * finds single-quoted SQL strings
 *         (can have "\'\'" to indicate "\'" or "\'\\s+\'" to indicate "")
 * finds double-quoted SQL identifiers
 *         (can have "\"\"" to indicate "\"" or "\"\\s+\"" to indicate "")
 */
#define RE_STRING "\'[^\']*\'(\?!\\s*\')|\"[^\"]*\"(\?!\\s*\")"

#define fields_MAX 20

#undef strerror

typedef uint8_t BYTE;//these types are not mandatory by the standard

typedef uint16_t BYTE2;

typedef uint64_t BYTE8;

typedef uint32_t BYTE4;

typedef sregex_token_iterator sti;

typedef sregex_iterator si;//not used

typedef chrono::system_clock::time_point my_time_point;

//indicates error in socket API calls
class network_exception : public runtime_error {
	using runtime_error::runtime_error;
};

//indicates error in database communication
class database_exception : public runtime_error {
	using runtime_error::runtime_error;
};

//indicates error in received PLs
class error_exception : public runtime_error {
	using runtime_error::runtime_error;
};

//indicates error in OS calls
class system_exception : public runtime_error {
	using runtime_error::runtime_error;
};

//indicates error in received messages
class message_exception : public runtime_error {
	using runtime_error::runtime_error;
};

//indicates unsupported operation in received PLs
class unsupported_exception : public runtime_error {
	using runtime_error::runtime_error;
};

struct header {
	bool I:1;//message Identifier field
	bool L:1;//message Length field
	bool D:1;//final Destination address field
	bool S:1;//final Source address field
	bool R:1;//cyclic Redundancy check field
	bool K:1;//end-to-end acKnowledgment
	bool C:1;//end-to-end Confidentiality
	bool A:1;//end-to-end Authenticity
	static const BYTE lookup_table[16];
	static BYTE reverse_byte(BYTE B) noexcept;
	BYTE get_as_byte() const noexcept;
	void put_as_byte(BYTE B) noexcept;
};

static_assert(sizeof(header) == sizeof(BYTE), "sizeof(header) != sizeof(BYTE)");

const BYTE header::lookup_table[16] = {
		0b0000, 0b1000, 0b0100, 0b1100,
		0b0010, 0b1010, 0b0110, 0b1110,
		0b0001, 0b1001, 0b0101, 0b1101,
		0b0011, 0b1011, 0b0111, 0b1111
};

BYTE header::reverse_byte(BYTE B) noexcept {
	return lookup_table[B & 0x0F] << 4 | lookup_table[(B & 0xF0) >> 4];
}

bool little_endian = true;

BYTE header::get_as_byte() const noexcept {
	return little_endian ? reverse_byte(*reinterpret_cast<const BYTE *>(this))
			: *reinterpret_cast<const BYTE *>(this);
}

void header::put_as_byte(BYTE B) noexcept {
	*reinterpret_cast<BYTE *>(this) = little_endian ? reverse_byte(B) : B;
}

struct formatted_message {
	BYTE4 CRC;//Cyclic Redundancy Check
	header HD;//message HeaDer
	BYTE ID;//message IDentifier
	BYTE2 LEN;//message LENgth
	BYTE8 DST;//final DeSTination address
	BYTE8 SRC;//final SouRCE address
	BYTE PL[1];//message PayLoad
	bool is_encrypted() const noexcept;
	bool is_signed() const;
	static void *operator new(size_t sz, int size);
	formatted_message(const formatted_message &fmsg);
	formatted_message &operator=(const formatted_message &fmsg);
	formatted_message(BYTE2 LEN);
	void encrypt();
	void decrypt();
	void sign();
	void verify();
};

static_assert(offsetof(formatted_message, DST) == 8, "offsetof(formatted_message, DST) != 8");

bool formatted_message::is_encrypted() const noexcept {
	return LEN > 0 && PL[0] == '@';
}

void *memcpy_endian(void *dst, const void *src, size_t len) noexcept;

bool formatted_message::is_signed() const {
	regex re(RE_STRING);
	string str;
	BYTE2 envelope_len = 0, encrypted_key_len;

	if (is_encrypted()) {
		THR(LEN < 3, message_exception("LEN < 3"));
		memcpy_endian(&envelope_len, PL + 1, 2);
		envelope_len += 3;
		THR(LEN < envelope_len, message_exception("LEN < 3 + ciphertext_len"));
		memcpy_endian(&encrypted_key_len, PL + envelope_len, 2);
		envelope_len += encrypted_key_len;
		THR(LEN < envelope_len, message_exception("LEN < 3 + len + encrypted_key_len"));
	}

	str.assign(reinterpret_cast<const char *>(PL + envelope_len), LEN - envelope_len);
	sti iter_begin(str.cbegin(), str.cend(), re, -1), iter_end;
	do {
		if (iter_begin->str().find('#') != string::npos) {
			return true;
		}
	} while (++iter_begin != iter_end);
	return false;
}

class protocol;//forward declaration

struct raw_message {
	BYTE8 imm_addr;//immediate remote address
	BYTE2 TML;//Total Message Length
	my_time_point TWR;//Time When Received
	protocol *proto;//the encapsulating protocol
	bool CCF;//Confidential Channel Flag
	bool ACF;//Authentic Channel Flag
	BYTE *msg;//physical message
	bool broadcast;
	bool override_implicit_rules;
	raw_message(const raw_message &rmsg) = delete;
	raw_message &operator=(const raw_message &rmsg) = delete;
	raw_message(BYTE *msg);
	~raw_message();
};

raw_message::raw_message(BYTE *msg) : imm_addr(), TML(), TWR(), proto(), CCF(),
		ACF(), msg(msg), broadcast(), override_implicit_rules() { }

raw_message::~raw_message() {
	delete[] msg;
}

struct ext_struct {
	char addr_id[28];//strlen("4294967296")=27
};

struct load_store_struct {
	bool load;
};

struct load_ack_struct { };

struct config_struct { };

struct refresh_next_timed_rule_time_struct {
	time_t next_timed_rule;
};

struct update_permissions_struct { };

struct manually_execute_timed_rule_struct {
	char username[256];//username cannot exceed
	int id;
};

/*
 * array: C array wrapper for easy indexing and hard finding
 * vector: array for easy pushing and popping on one end,
 * 		easy indexing, hard finding, and hard inserting and erasing
 * deque: array for easy pushing and popping on both ends,
 * 		easy indexing, hard finding, and hard inserting and erasing
 * forward_list: linked list for easy pushing and popping on one end,
 * 		hard indexing, hard finding, and easy inserting and erasing
 * list: linked list for easy pushing and popping on both ends,
 * 		hard indexing, hard finding, and easy inserting and erasing
 * stack: LIFO adapter
 * queue: FIFO adapter
 * priority_queue: priority adapter
 * span: a pair of iterators
 * hive: array for easy pushing and popping on one end,
 * 		hard indexing and finding, hard inserting, and easy erasing
 */
struct remote {
	map<BYTE8, map<BYTE, my_time_point> *> DST_ID_TWR;
	BYTE out_ID;
	map<protocol *, map<BYTE8, my_time_point> *> proto_iSRC_TWR;
};

struct send_inject_struct {
	char proto_id[13];//strlen("my4294967296")=12
	BYTE imm_addr[8];
	bool CCF;
	bool ACF;
	bool send;
	bool broadcast;
	bool override_implicit_rules;
	int message_length;
	BYTE message_content[1];
	send_inject_struct(const send_inject_struct &);
	send_inject_struct &operator=(const send_inject_struct &);
	static void *operator new(size_t sz, int size);
	send_inject_struct(int message_length);
};

enum struct datatypes : int {//data types are written out as PostgreSQL writes them out
	BOOLEAN = 0,
	INTERVAL,
	NUMERIC,//integer or float
	TEXT,//string or unicode string//national strings are not supported
	TIMESTAMP,//with or without time zone
	DATE,
	TIME,//with or without time zone
	BYTEA//binary string
};

union typedetails {
	struct { } text;
	struct {
		int precision;
	} interval;
	struct {
		int precision;
		int scale;
	} numeric;
	struct { } boolean;
	struct {
		int precision;
		bool with_time_zone;
	} timestamp;
	struct { } date;
	struct {
		int precision;
		bool with_time_zone;
	} time;
	struct { } bytea;
};

struct configuration {
	bool forward_messages;
	bool use_internet_switch_algorithm;
	int nsecs_id;
	int nsecs_src;
	bool trust_everyone;
	BYTE8 default_gateway;
};

map<string, configuration *> username_configuration;

my_time_point beginning;

map<string, string *> table_user;

PGconn *conn;

string local_FROM(" FROM t");

BYTE8 local_addr = 0xBABADEDA'DECACECA;

map<BYTE8, remote *> addr_remote;//user can edit this via web configuration

const char text_to_hex[104] = "xxxxxxxx" "xxxxxxxx" "xxxxxxxx" "xxxxxxxx" "xxxxxxxx" "xxxxxxxx"
		"\x00\x01\x02\x03\x04\x05\x06\x07" "\x08\x09xxxxxx" "xxxxxxxx" "xxxxxxxx" "xxxxxxxx"
		"xxxxxxxx" "x\x0A\x0B\x0C\x0D\x0E\x0F";//converts [0-9a-f] to 0..15

default_random_engine dre;

uniform_int_distribution<int> uid(0, 255);

const char hex_to_text[17] = "0123456789abcdef";//converts 0..15 to [0-9a-f]

my_time_point (* const my_now)() noexcept = chrono::system_clock::now;

mqd_t main_mq;

mqd_t update_permissions_mq;

mqd_t ext_mq;

mqd_t send_inject_mq;

mqd_t prlimit_pid_mq;

mqd_t prlimit_ack_mq;

mqd_t load_store_mq;

mqd_t load_ack_mq;

mqd_t config_mq;

mqd_t refresh_next_timed_rule_time_mq;

mqd_t manually_execute_timed_rule_mq;

vector<protocol *> protocols;

time_t next_timed_rule = 0;

multimap<protocol *, BYTE8> local_proto_iaddr;

BYTE8 EUI48_to_EUI64(BYTE8 EUI48) noexcept;

const char *BYTE8_to_c17charp(BYTE8 address);

BYTE8 EUI64_to_EUI48(BYTE8 EUI64) noexcept;//not used

void populate_local_proto_iaddr();

void determine_local_addr();

PGresult *execcheckreturn(string query);

extern "C" { PG_FUNCTION_INFO_V1(ext); }

extern "C" { PG_FUNCTION_INFO_V1(send_inject); }

void ext2(const ext_struct &es);

void send_inject2(const send_inject_struct &sis);

void main_loop();

void send_control(string payload, BYTE8 DST, BYTE8 SRC);

formatted_message *receive_formatted_message();

raw_message *receive_raw_message();

void encode_bytes_to_stream(ostream &stream, const BYTE *bytes, size_t len);

void execute_semicolon_separated_commands(const char *preamble, formatted_message &fmsg,
		raw_message &rmsg, const char *commands_in_c);

void decode_bytes_from_stream(istream &stream, BYTE *bytes, size_t len);

void send_inject_from_rule(const char *message, const char *proto_id, const char *imm_addr,
		bool CCF, bool ACF, bool broadcast, bool override_implicit_rules, bool send);

void apply_rule_beginning(PGresult *res_rules, int &current_id, int i, const char *type,
		string &current_username);

void insert_message(const formatted_message &fmsg, const raw_message &rmsg);

formatted_message *apply_rules(formatted_message *fmsg, raw_message *rmsg, bool send);

void select_message(formatted_message &fmsg, raw_message &rmsg);

void apply_rule_end(PGresult *&res_rules, int current_id, int &i, int &j, int offset,
		string &select, string &current_username);

BYTE8 c17charp_to_BYTE8(const char *address);

ostream &operator<<(ostream &os, const raw_message &rmsg) noexcept;

ostream &operator<<(ostream &os, const my_time_point &point) noexcept;

istream &operator>>(istream &is, my_time_point &point) noexcept;

void print_message_c(ostream &os, const BYTE *msg, size_t length) noexcept;

extern "C" { PG_FUNCTION_INFO_V1(load_store); }

extern "C" { PG_FUNCTION_INFO_V1(config); }

extern "C" { PG_FUNCTION_INFO_V1(refresh_next_timed_rule_time); }

extern "C" { PG_FUNCTION_INFO_V1(update_permissions); }

extern "C" { PG_FUNCTION_INFO_V1(manually_execute_timed_rule); }

void load_store2_load();

void load_store2_store();

void config2();

void encode_message(formatted_message &fmsg, raw_message &rmsg);

void refresh_next_timed_rule_time2(const refresh_next_timed_rule_time_struct &rntrts);

void update_permissions2();

void manually_execute_timed_rule2(const manually_execute_timed_rule_struct &metrs);

void decode_message(raw_message &rmsg, formatted_message &fmsg);

ostream &operator<<(ostream &os, const protocol &proto) noexcept;

void *memcpy_reverse(void *dst, const void *src, size_t size) noexcept;

void security_check_for_sending(formatted_message &fmsg, raw_message &rmsg);

BYTE4 givecrc32c(const BYTE *msg, BYTE2 len) noexcept;

ostream &operator<<(ostream &os, const formatted_message &fmsg) noexcept;

void security_check_for_receiving(raw_message &rmsg, formatted_message &fmsg);

ostream &operator<<(ostream &os, const header &HD) noexcept;

void convert_select(string &query, string remote_FROM);

void print_message_cpp(ostream &os, string msg) noexcept;

void format_select(string &query);

void sub(string query, string _id, BYTE8 address);

void unsub(string _id, BYTE8 address);

void sel(string query, BYTE8 address);

bool is_select(string query);

bool clean_select(string query);

PGresult *formatsendreturn(PGresult *res, BYTE8 DST);

void send_formatted_message(formatted_message *fmsg);

void send_raw_message(raw_message *rmsg);

void ins(string data, BYTE8 address);

void format_insert(string &data, vector<string> &columns, vector<string> &types,
		vector<datatypes> &dt, vector<typedetails> &td);

void format_insert_header(sti &iter_begin, sti &iter_end, string &temp, vector<string> &columns,
		string &first, string &second, bool &no_t);

void format_insert_body(sti &iter_begin, sti &iter_end, string &temp, vector<datatypes> &dt,
		vector<typedetails> &td, string &first, string &second, bool &no_t);

bool clean_insert(string data);

void create_table(string address, vector<string> &columns, vector<string> &types);

void alter_table(string address, vector<string> &columns, const vector<string> &types);

void initialize_vars();

void destroy_vars();

int find_next_lower_device(int sock, ifreq &ifr, int &i);

int find_next_lower_bluetooth(int sock, hci_dev_info &hdi, int &i);

void sig_ign(int sig);

extern "C" const char *SSL_error_string(int e, char *buf);

string SSL_give_error(const SSL *ssl, int ret);

in_addr BYTE8_to_ia(BYTE8 address) noexcept;

BYTE8 ia_to_BYTE8(in_addr ia) noexcept;

protocol *find_protocol_by_id(const char *id);

protocol *find_protocol_by_name(const char *name);

bool check_permissions(const char *tablename, BYTE8 address);

class protocol {

private:

	static atomic_int current_id;

	static string unique_id() noexcept;

	string my_id;

	thread *recv_all_thread;

	thread *send_all_thread;

	mqd_t my_mq;

	static void recv_all(protocol *proto);

	static void send_all(protocol *proto);

protected:

	static atomic_int highest_sock;

	atomic_bool run;

	static void check_sock(int new_sock) noexcept;

	virtual raw_message *recv_once() = 0;

	virtual void send_once(raw_message *rmsg) = 0;

	protocol(const protocol &p) = delete;

	protocol &operator=(const protocol &p) = delete;

public:

	string get_my_id() const noexcept { return my_id; }

	mqd_t get_my_mq() const noexcept { return my_mq; }

	bool is_running() const noexcept { return recv_all_thread != nullptr; }

	virtual bool can_secure_with_C(raw_message &rmsg) const noexcept = 0;

	virtual bool can_secure_with_A(raw_message &rmsg) const noexcept = 0;

	virtual void start();

	virtual void stop();

	protocol();

	virtual ~protocol();

};

template<typename type>
void instantiate_protocol_if_enabled();

const char *get_typename(const type_info &type);

protocol::protocol() : my_id(unique_id()), recv_all_thread(nullptr), run(true) { }

void protocol::start() {
	mq_attr ma = { 0, 64, sizeof(raw_message *) };

	THR(is_running(), system_exception("starting started protocol"));
	my_mq = mq_open(my_id.c_str(), O_RDWR | O_CREAT, 0777, &ma);
	THR(my_mq < 0, system_exception("cannot open my_mq"));
	recv_all_thread = new thread(recv_all, this);//@suppress("Symbol is not resolved")
	send_all_thread = new thread(send_all, this);//@suppress("Symbol is not resolved")
	LOG_CPP("created threads " << recv_all_thread->get_id() << " and " << send_all_thread->get_id()
			<< " for protocol " << my_id << endl);
}

protocol::~protocol() {
	try {
		stop();
	} catch (...) { }
}

void protocol::stop() {
	int pid = getpid();

	THR(!is_running(), system_exception("stopping stopped protocol"));
	run = false;
	kill(pid, SIGUSR1);
	send_all_thread->join();
	LOG_CPP("stopped thread " << send_all_thread->get_id() << endl);
	delete send_all_thread;
	kill(pid, SIGUSR2);
	recv_all_thread->join();
	LOG_CPP("stopped thread " << recv_all_thread->get_id() << endl);
	delete recv_all_thread;
	THR(mq_unlink(my_id.c_str()) < 0, system_exception("cannot unlink my_mq"));
	recv_all_thread = nullptr;
}

//this function is executed in another thread!!!
void protocol::recv_all(protocol *proto) {
	raw_message *rmsg;

	LOG_CPP("started recv_all thread " << this_thread::get_id() << " for class "
			<< typeid(*proto).name() << " and id " << proto->get_my_id() << endl);
	while (true) {
		rmsg = proto->recv_once();
		if (!proto->run) {
			return;
		}
		THR(rmsg == nullptr, system_exception("recv_all received nullptr"));
		THR(rmsg->proto != proto, system_exception("recv_all received other proto's message"));
		THR(mq_send(main_mq, reinterpret_cast<char*>(&rmsg), sizeof(rmsg), 0) < 0,
				system_exception("cannot send to main_mq"));
	}
}

//this function is executed in another thread!!!
void protocol::send_all(protocol *proto) {
	raw_message *rmsg;

	LOG_CPP("started send_all thread " << this_thread::get_id() << " for class "
			<< typeid(*proto).name() << " and id " << proto->get_my_id() << endl);
	while (true) {
		if (mq_receive(proto->get_my_mq(),
				reinterpret_cast<char *>(&rmsg), sizeof(rmsg), nullptr) < 0) {
			THR(errno != EINTR, system_exception("cannot receive from my_mq"));
			continue;
		}
		if (!proto->run) {
			return;
		}
		proto->send_once(rmsg);
	}
}

atomic_int protocol::current_id(0);

string protocol::unique_id() noexcept {
	ostringstream oss("/my", oss.out | oss.ate);

	oss << current_id++;
	return oss.str();
}

atomic_int protocol::highest_sock(0);

void protocol::check_sock(int new_sock) noexcept {
	if (new_sock > highest_sock) {
		highest_sock = new_sock;
	}
}

struct opensock {
	bool CCF;
	bool ACF;
	int sock;
	BYTE8 imm_addr;
	SSL *ssl;
};

#define TCP_PORT 60000

#define TLS_PORT 60001

#define TCP_BACKLOG 10

#define TLS_BACKLOG 10

static_assert(sizeof(in_addr) == sizeof(BYTE4), "sizeof(in_addr) != sizeof(BYTE4)");

EVP_CIPHER_CTX *cipherctx;

EVP_MD_CTX *mdctx;

const EVP_CIPHER *ciphertype;

const EVP_MD *mdtype;

int blocksizetimes2minus1;

int ivlength;

#ifdef SIGNAL
signal_context *global_context;

signal_protocol_store_context *store_context;

pthread_mutex_t global_mutex;

pthread_mutexattr_t global_mutexattr;
#endif

in_addr BYTE8_to_ia(BYTE8 address) noexcept {
	in_addr ia;

	if (little_endian) {
		memcpy_reverse(&ia, &address, 4);
	} else {
		memcpy(&ia, reinterpret_cast<BYTE *>(&address) + 4, 4);
	}
	return ia;
}

#ifdef SIGNAL
void test_lock(void *user_data);

void test_unlock(void *user_data);
#endif

EVP_PKEY *get_private_key(BYTE8 addr);

EVP_PKEY *get_public_key(BYTE8 addr);

void serialize_digital_envelope(const BYTE *ciphertext, int ciphertext_len,
		const BYTE *encrypted_key, int encrypted_key_len, const BYTE *iv, BYTE *dst, int &dst_len);

void deserialize_digital_envelope(const BYTE *src, int src_len, const BYTE *&ciphertext,
		int &ciphertext_len, const BYTE *&encrypted_key, int &encrypted_key_len, const BYTE *&iv);

void serialize_digital_signature(const BYTE *signature, int signature_len, BYTE *dst, int &dst_len);

void deserialize_digital_signature(const BYTE *src, int src_len, const BYTE *&signature,
		int &signature_len);

void seal_digital_envelope(EVP_PKEY *receivers_public_key, const BYTE *plaintext,
		int plaintext_len, BYTE *&ciphertext, int &ciphertext_len, BYTE *&encrypted_key,
		int &encrypted_key_len, BYTE *&iv);

void open_digital_envelope(EVP_PKEY *receivers_private_key, const BYTE *ciphertext,
		int ciphertext_len, const BYTE *encrypted_key, int encrypted_key_len, const BYTE *iv,
		BYTE *plaintext, int &plaintext_len);

void create_digital_signature(EVP_PKEY *senders_private_key, const BYTE *plaintext,
		int plaintext_len, BYTE *&signature, int &signature_len);

void verify_digital_signature(EVP_PKEY *senders_public_key, const BYTE *plaintext,
		int plaintext_len, const BYTE *signature, int signature_len);

BYTE8 ia_to_BYTE8(in_addr ia) noexcept {
	BYTE8 address = 0x00000000'00000000;

	if (little_endian) {
		memcpy_reverse(&address, &ia, 4);
	} else {
		memcpy(reinterpret_cast<BYTE *>(&address) + 4, &ia, 4);
	}
	return address;
}

#ifdef SIGNAL
void test_lock(void *) {
	pthread_mutex_lock(&global_mutex);
}

void test_unlock(void *) {
	pthread_mutex_unlock(&global_mutex);
}
#endif

void *formatted_message::operator new(size_t sz, int size) {
	return ::operator new(sz + size - 1);
}

formatted_message::formatted_message(const formatted_message &fmsg) {
	memcpy(this, &fmsg, sizeof(fmsg) - 1 + fmsg.LEN);
}

formatted_message &formatted_message::operator=(const formatted_message &fmsg) {
	memcpy(this, &fmsg, sizeof(fmsg) - 1 + fmsg.LEN);
	return *this;
}

void *send_inject_struct::operator new(size_t sz, int size) {
	return ::operator new(sz + size - 1);
}

send_inject_struct::send_inject_struct(const send_inject_struct &sis) {
	memcpy(this, &sis, sizeof(sis) - 1 + sis.message_length);
}

send_inject_struct &send_inject_struct::operator=(const send_inject_struct &sis) {
	memcpy(this, &sis, sizeof(sis) - 1 + sis.message_length);
	return *this;
}

void formatted_message::encrypt() {
	EVP_PKEY *receivers_public_key = get_public_key(DST);
	BYTE *ciphertext, *encrypted_key, *iv;
	int ciphertext_len, encrypted_key_len, len = PL_MAX;

	seal_digital_envelope(receivers_public_key, PL, LEN, ciphertext, ciphertext_len,
			encrypted_key, encrypted_key_len, iv);
	serialize_digital_envelope(ciphertext, ciphertext_len, encrypted_key, encrypted_key_len, iv,
			PL, len);
	LEN = len;
	delete[] ciphertext;
	delete[] encrypted_key;
	delete[] iv;
	EVP_PKEY_free(receivers_public_key);
}

void formatted_message::decrypt() {
	EVP_PKEY *receivers_private_key = get_private_key(SRC);
	const BYTE *ciphertext, *encrypted_key, *iv;
	int ciphertext_len, encrypted_key_len, len;

	deserialize_digital_envelope(PL, LEN, ciphertext, ciphertext_len, encrypted_key,
			encrypted_key_len, iv);
	open_digital_envelope(receivers_private_key, ciphertext, ciphertext_len, encrypted_key,
			encrypted_key_len, iv, PL, len);
	LEN = len;
	EVP_PKEY_free(receivers_private_key);
}

void formatted_message::sign() {
	EVP_PKEY *senders_private_key = get_private_key(SRC);
	BYTE *signature;
	int signature_len, len = PL_MAX - LEN;

	create_digital_signature(senders_private_key, PL, LEN, signature, signature_len);
	serialize_digital_signature(signature, signature_len, PL + LEN, len);
	LEN += len;
	delete[] signature;
	EVP_PKEY_free(senders_private_key);
}

formatted_message::formatted_message(BYTE2 LEN) : CRC(), HD(), ID(), LEN(LEN), DST(), SRC() { }

send_inject_struct::send_inject_struct(int message_length) : CCF(), ACF(), send(),
		broadcast(), override_implicit_rules(), message_length(message_length) { }

void formatted_message::verify() {
	EVP_PKEY *senders_public_key = get_public_key(DST);
	const BYTE *signature;
	int signature_len, len;
	regex re(RE_STRING);
	string str(reinterpret_cast<char *>(PL), LEN);
	string::const_iterator cb = str.cbegin();
	sti iter_begin(cb, str.cend(), re, -1), iter_end;

	do {
		len = iter_begin->str().find('#');
		if (len != static_cast<int>(string::npos)) {
			len += iter_begin->first - cb;
			break;
		}
	} while (++iter_begin != iter_end);
	THR(len == static_cast<int>(string::npos), message_exception("no signature char found"));
	deserialize_digital_signature(PL + len, LEN - len, signature, signature_len);
	verify_digital_signature(senders_public_key, PL, len, signature, signature_len);
	LEN = len;
	EVP_PKEY_free(senders_public_key);
}

EVP_PKEY *get_private_key(BYTE8 addr) {
	FILE *file = fopen(("privateKeys/"s + BYTE8_to_c17charp(addr) + ".pem").c_str(), "rb");
	EVP_PKEY *retval;

	THR(file == nullptr, system_exception("cannot find privateKey"));
	retval = PEM_read_PrivateKey(file, nullptr, nullptr, nullptr);
	THR(retval == nullptr, system_exception("cannot read privateKey"));
	fclose(file);
	return retval;
}

EVP_PKEY *get_public_key(BYTE8 addr) {
	FILE *file = fopen(("certificates/"s + BYTE8_to_c17charp(addr) + ".pem").c_str(), "rb");
	X509 *x509;
	EVP_PKEY *retval;

	THR(file == nullptr, system_exception("cannot find certificate"));
	x509 = PEM_read_X509(file, nullptr, nullptr, nullptr);
	THR(x509 == nullptr, system_exception("cannot read certificate"));
	fclose(file);
	retval = X509_get_pubkey(x509);
	THR(retval == nullptr, system_exception("cannot get pubkey"));
	X509_free(x509);
	return retval;
}

void serialize_digital_envelope(const BYTE *ciphertext, int ciphertext_len,
		const BYTE *encrypted_key, int encrypted_key_len, const BYTE *iv, BYTE *dst, int &dst_len) {
	int i = ciphertext_len + encrypted_key_len + ivlength + 5;

	THR(i > dst_len, message_exception("too big an envelope created"));
	dst_len = i;
	*dst = '@';
	if (little_endian) {
		memcpy_reverse(dst + 1, &ciphertext_len, 2);
		memcpy_reverse(dst + ciphertext_len + 3, &encrypted_key_len, 2);
	} else {
		memcpy(dst + 1, reinterpret_cast<BYTE *>(&ciphertext_len) + sizeof(int) - 2, 2);
		memcpy(dst + ciphertext_len + 3,
				reinterpret_cast<BYTE *>(&encrypted_key_len) + sizeof(int) - 2, 2);
	}
	memcpy(dst + 3, ciphertext, ciphertext_len);
	memcpy(dst + ciphertext_len + 5, encrypted_key, encrypted_key_len);
	memcpy(dst + ciphertext_len + encrypted_key_len + 5, iv, ivlength);
}

void deserialize_digital_envelope(const BYTE *src, int src_len, const BYTE *&ciphertext,
		int &ciphertext_len, const BYTE *&encrypted_key, int &encrypted_key_len, const BYTE *&iv) {
	THR(*src != '@', message_exception("envelope malformed"));
	THR(3 < src_len, message_exception("3 < src_len"));
	if (little_endian) {
		memcpy_reverse(&ciphertext_len, src + 1, 2);
		THR(ciphertext_len + 5 < src_len, message_exception("ciphertext_len + 5 < src_len"));
		memcpy_reverse(&encrypted_key_len, src + ciphertext_len + 3, 2);
	} else {
		memcpy(reinterpret_cast<BYTE *>(&ciphertext_len) + sizeof(int) - 2, src + 1, 2);
		THR(ciphertext_len + 5 < src_len, message_exception("ciphertext_len + 5 < src_len"));
		memcpy(reinterpret_cast<BYTE *>(&encrypted_key_len) + sizeof(int) - 2,
				src + ciphertext_len + 3, 2);
	}
	THR(encrypted_key_len + ciphertext_len + ivlength + 5 < src_len,
			message_exception("encrypted_key_len + ciphertext_len + iv_length + 5 < src_len"));
	ciphertext = src + 3;
	encrypted_key = ciphertext + ciphertext_len + 2;
	iv = encrypted_key + encrypted_key_len;
}

void serialize_digital_signature(const BYTE *signature, int signature_len, BYTE *dst,
		int &dst_len) {
	int i = signature_len + 1;

	THR(i > dst_len, message_exception("too big a signature created"));
	dst_len = i;
	*dst = '#';
	memcpy(dst + 1, signature, signature_len);
}

void deserialize_digital_signature(const BYTE *src, int src_len, const BYTE *&signature,
		int &signature_len) {
	THR(*src != '#', message_exception("signature malformed"));
	signature_len = src_len - 1;
	signature = src + 1;
}

void seal_digital_envelope(EVP_PKEY *receivers_public_key, const BYTE *plaintext,
		int plaintext_len, BYTE *&ciphertext, int &ciphertext_len, BYTE *&encrypted_key,
		int &encrypted_key_len, BYTE *&iv) {
	int len;

	THR(EVP_CIPHER_CTX_reset(cipherctx) == 0, system_exception("cannot reset cipherctx"));
	encrypted_key = new BYTE[EVP_PKEY_size(receivers_public_key)];
	iv = new BYTE[ivlength];
	THR(EVP_SealInit(cipherctx, ciphertype, &encrypted_key, &encrypted_key_len, iv,
			&receivers_public_key, 1) == 0, message_exception("cannot sealinit"));
	ciphertext = new BYTE[plaintext_len + blocksizetimes2minus1];
	THR(EVP_SealUpdate(cipherctx, ciphertext, &len, plaintext, plaintext_len) == 0,
			message_exception("cannot sealupdate"));
	ciphertext_len = len;
	THR(EVP_SealFinal(cipherctx, ciphertext + len, &len) == 0,
			message_exception("cannot sealfinish"));
	ciphertext_len += len;
}

void open_digital_envelope(EVP_PKEY *receivers_private_key, const BYTE *ciphertext,
		int ciphertext_len, const BYTE *encrypted_key, int encrypted_key_len, const BYTE *iv,
		BYTE *plaintext, int &plaintext_len) {
	int len;

	THR(plaintext_len < ciphertext_len + blocksizetimes2minus1,
			message_exception("no room for plaintext"));
	THR(EVP_CIPHER_CTX_reset(cipherctx) == 0, system_exception("cannot reset cipherctx"));
	THR(EVP_OpenInit(cipherctx, ciphertype, encrypted_key, encrypted_key_len, iv,
			receivers_private_key) == 0, message_exception("cannot openinit"));
	THR(EVP_OpenUpdate(cipherctx, plaintext, &len, ciphertext, ciphertext_len) == 0,
			message_exception("cannot openupdate"));
	plaintext_len = len;
	THR(EVP_OpenFinal(cipherctx, plaintext + len, &len) == 0,
			message_exception("cannot openfinal"));
	plaintext_len += len;
}

void create_digital_signature(EVP_PKEY *senders_private_key, const BYTE *plaintext,
		int plaintext_len, BYTE *&signature, int &signature_len) {
	unsigned long len;

	THR(EVP_MD_CTX_reset(mdctx) == 0, system_exception("cannot reset mdctx"));
	THR(EVP_DigestSignInit(mdctx, nullptr, mdtype, nullptr, senders_private_key) == 0,
			message_exception("cannot digestsigninit"));
	THR(EVP_DigestSignUpdate(mdctx, plaintext, plaintext_len) == 0,
			message_exception("cannot digestsignupdate"));
	THR(EVP_DigestSignFinal(mdctx, nullptr, &len) == 0,
			message_exception("cannot digestsignfinal1"));
	signature_len = len;
	signature = new BYTE[signature_len];
	THR(EVP_DigestSignFinal(mdctx, signature, &len) == 0,
			message_exception("cannot digestsignfinal2"));
}

void verify_digital_signature(EVP_PKEY *senders_public_key, const BYTE *plaintext,
		int plaintext_len, const BYTE *signature, int signature_len) {
	THR(EVP_MD_CTX_reset(mdctx) == 0, system_exception("cannot reset mdctx"));
	THR(EVP_DigestVerifyInit(mdctx, nullptr, mdtype, nullptr, senders_public_key) == 0,
			message_exception("cannot digestverifyinit"));
	THR(EVP_DigestVerifyUpdate(mdctx, plaintext, plaintext_len) == 0,
			message_exception("cannot digestverifyupdate"));
	THR(EVP_DigestVerifyFinal(mdctx, signature, signature_len) == 0,
			message_exception("cannot digestverifyfinal"));
}

extern "C" const char *SSL_error_string(int e, char *buf) {
	const char *retval = "";

	switch (e) {
	case SSL_ERROR_NONE:
		retval = "NONE";
		break;
	case SSL_ERROR_WANT_READ:
		retval = "WANT_READ";
		break;
	case SSL_ERROR_WANT_WRITE:
		retval = "WANT_WRITE";
		break;
	case SSL_ERROR_WANT_CONNECT:
		retval = "WANT_CONNECT";
		break;
	case SSL_ERROR_WANT_ACCEPT:
		retval = "WANT_ACCEPT";
		break;
	case SSL_ERROR_WANT_X509_LOOKUP:
		retval = "WANT_X509_LOOKUP";
		break;
	case SSL_ERROR_WANT_ASYNC:
		retval = "WANT_ASYNC";
		break;
	case SSL_ERROR_WANT_ASYNC_JOB:
		retval = "WANT_ASYNC_JOB";
		break;
	case SSL_ERROR_SYSCALL:
		retval = "SYSCALL";
		break;
	case SSL_ERROR_SSL:
		retval = "SSL";
	}
	if (buf != NULL) {
		strcpy(buf, retval);
	}
	return retval;
}

string SSL_give_error(const SSL *ssl, int ret) {
	string retval(SSL_error_string(SSL_get_error(ssl, ret), nullptr));

	if (retval == "SYSCALL") {
		retval += strerror(errno);
	} else if (retval == "SSL") {
		retval += ERR_error_string(ERR_get_error(), nullptr);
	}
	return retval;
}

#define ADVERTISING_INTERVAL_MIN 1024

#define ADVERTISING_INTERVAL_MAX 1024

#define LE_SCAN_INTERVAL 4096

#define LE_SCAN_WINDOW 4096

#define EN_ADV_TO_DIS_ADV 10000us

#define CHAN_MAP 0b00000001

#define BROADCAST_PLACEHOLDER 0xFFFFFFFF'FFFFFFFF

class ble : public protocol {

private:

	int sock;

	map<BYTE8, raw_message *> to_send_down_later;

protected:

	virtual void send_once(raw_message *rmsg) override;

	virtual raw_message *recv_once() override;

public:

	virtual bool can_secure_with_C(raw_message &rmsg) const noexcept override;

	virtual bool can_secure_with_A(raw_message &rmsg) const noexcept override;

	virtual void start() override;

	virtual void stop() override;

};

void ble::send_once(raw_message *rmsg) {
	to_send_down_later.insert(make_pair(rmsg->broadcast ? BROADCAST_PLACEHOLDER : rmsg->imm_addr,
			rmsg));
}

raw_message *ble::recv_once() {
	le_advertising_info *lai;
	BYTE status, buf[HCI_MAX_EVENT_SIZE];
	BYTE8 temp;
	raw_message *rmsg;
	hci_request rq;
	le_set_advertising_data_cp ad;
	le_set_advertise_enable_cp ae;
	le_set_scan_enable_cp se;
	hci_event_hdr *hdr;
	evt_le_meta_event *me;
	map<BYTE8, raw_message *>::iterator iter;
	hci_filter flt;
	bool gatt;
	int retval;

	hci_filter_clear(&flt);
	hci_filter_all_ptypes(&flt);
	hci_filter_all_events(&flt);
	setsockopt(sock, SOL_HCI, HCI_FILTER, &flt, sizeof(flt));

	do {
		retval = read(sock, buf, sizeof(buf));
		if (!run) {
			return nullptr;
		}
		THR(retval < 0, network_exception("cannot read sock"));
		hdr = reinterpret_cast<hci_event_hdr *>(buf + 1);//unaligned pointer!
		THR(hdr->evt != EVT_LE_META_EVENT, message_exception("wrong event"));
		me = reinterpret_cast<evt_le_meta_event *>(buf + 1 + HCI_EVENT_HDR_SIZE);
				//unaligned pointer!
		THR(me->subevent != EVT_LE_ADVERTISING_REPORT, message_exception("wrong subevent"));
		lai = reinterpret_cast<le_advertising_info *>((me->data+1));
	} while(lai->evt_type != 0x03);
	THR(lai->evt_type != 0x03, message_exception("wrong event type"));

	gatt = lai->length > 1 && lai->data[1] == 0xFF;
	if (gatt) {
		lai->length -= 4;
	}
	rmsg = new raw_message(new BYTE[lai->length]);
	if (little_endian) {
		memcpy_reverse(&temp, &lai->bdaddr, 6);
	} else {
		memcpy(reinterpret_cast<BYTE *>(&temp) + 2, &lai->bdaddr, 6);
	}
	rmsg->imm_addr = EUI48_to_EUI64(temp);
	memcpy(rmsg->msg, gatt ? lai->data+4 : lai->data, lai->length);
	rmsg->CCF = false;
	rmsg->proto = this;
	rmsg->ACF = false;
	rmsg->TML = lai->length;
	rmsg->TWR = my_now();
	rmsg->broadcast = true;
	rmsg->override_implicit_rules = false;

	iter = to_send_down_later.find(rmsg->imm_addr);
	if (iter != to_send_down_later.end()) {
		iter = to_send_down_later.find(BROADCAST_PLACEHOLDER);
	}
	if (iter != to_send_down_later.end()) {
		se.enable = 0x00;
		se.filter_dup = 0x00;
		rq.ogf = OGF_LE_CTL;
		rq.ocf = OCF_LE_SET_SCAN_ENABLE;
		rq.cparam = &se;
		rq.clen = LE_SET_SCAN_ENABLE_CP_SIZE;
		rq.rparam = &status;
		rq.rlen = 1;
		THR(hci_send_req(sock, &rq, 1000) < 0, network_exception("cannot hci_send_req"));
		THR(status > 0, network_exception("cannot command complete"));

		ad.length = iter->second->TML;
		memcpy(ad.data, iter->second->msg, ad.length);
		rq.ocf = OCF_LE_SET_ADVERTISING_DATA;
		rq.cparam = &ad;
		rq.clen = LE_SET_ADVERTISING_DATA_CP_SIZE;
		THR(hci_send_req(sock, &rq, 1000) < 0, network_exception("cannot hci_send_req"));
		THR(status > 0, network_exception("cannot command complete"));

		ae.enable = 0x01;
		rq.ocf = OCF_LE_SET_ADVERTISE_ENABLE;
		rq.cparam = &ae;
		rq.clen = LE_SET_ADVERTISE_ENABLE_CP_SIZE;
		THR(hci_send_req(sock, &rq, 1000) < 0, network_exception("cannot hci_send_req"));
		THR(status > 0, network_exception("cannot command complete"));
		this_thread::sleep_for(EN_ADV_TO_DIS_ADV);

		ae.enable = 0x00;
		THR(hci_send_req(sock, &rq, 1000) < 0, network_exception("cannot hci_send_req"));
		THR(status > 0, network_exception("cannot command complete"));
		delete iter->second;
		to_send_down_later.erase(iter);

		se.enable = 0x01;
		rq.ocf = OCF_LE_SET_SCAN_ENABLE;
		rq.cparam = &se;
		rq.clen = LE_SET_SCAN_ENABLE_CP_SIZE;
		THR(hci_send_req(sock, &rq, 1000) < 0, network_exception("cannot hci_send_req"));
		THR(status > 0, network_exception("cannot command complete"));
	}
	return rmsg;
}

bool ble::can_secure_with_C(raw_message &) const noexcept { return false; }

bool ble::can_secure_with_A(raw_message &) const noexcept { return false; }

void ble::start() {
	sockaddr_hci sa;
	hci_request rq;
	le_set_advertising_parameters_cp ap;
	le_set_scan_parameters_cp sp;
	le_set_scan_enable_cp se;
	BYTE status;

	THR(is_running(), system_exception("starting started protocol"));

	sock = socket(AF_BLUETOOTH, SOCK_RAW, BTPROTO_HCI);
	THR(sock < 0, network_exception("cannot socket sock"));
	check_sock(sock);
	sa.hci_family = AF_BLUETOOTH;
	sa.hci_dev = 0;
	sa.hci_channel = HCI_CHANNEL_RAW;
	THR(bind(sock, reinterpret_cast<sockaddr *>(&sa), sizeof(sa)) < 0,
			network_exception("cannot bind sock"));

	ap.min_interval = ADVERTISING_INTERVAL_MIN;
	ap.max_interval = ADVERTISING_INTERVAL_MAX;
	ap.advtype = 0x03;
	ap.own_bdaddr_type = 0x0;
	ap.direct_bdaddr_type = 0x0;
	memset(&ap.direct_bdaddr, 0, sizeof(ap.direct_bdaddr));
	ap.chan_map = CHAN_MAP;
	ap.filter = 0x00;
	rq.ogf = OGF_LE_CTL;
	rq.ocf = OCF_LE_SET_ADVERTISING_PARAMETERS;
	rq.cparam = &ap;
	rq.clen = LE_SET_ADVERTISING_PARAMETERS_CP_SIZE;
	rq.rparam = &status;
	rq.rlen = 1;
	THR(hci_send_req(sock, &rq, 1000) < 0, network_exception("cannot hci_send_req"));
	THR(status > 0, network_exception("cannot command complete"));

	se.enable = 0x0;
	se.filter_dup = 0x00;
	rq.ogf = OGF_LE_CTL;
	rq.ocf = OCF_LE_SET_SCAN_ENABLE;
	rq.cparam = &se;
	rq.clen = LE_SET_SCAN_ENABLE_CP_SIZE;
	rq.rparam = &status;
	rq.rlen = 1;
	THR(hci_send_req(sock, &rq, 1000) < 0, network_exception("cannot hci_send_req"));
	THR(status > 0, network_exception("cannot command complete"));

	sp.type = 0x00;
	sp.interval = LE_SCAN_INTERVAL;
	sp.window = LE_SCAN_WINDOW;
	sp.own_bdaddr_type = 0x00;
	sp.filter = 0x00;
	rq.ogf = OGF_LE_CTL;
	rq.ocf = OCF_LE_SET_SCAN_PARAMETERS;
	rq.cparam = &sp;
	rq.clen = LE_SET_SCAN_PARAMETERS_CP_SIZE;
	rq.rparam = &status;
	rq.rlen = 1;
	THR(hci_send_req(sock, &rq, 1000) < 0, network_exception("cannot hci_send_req"));
	THR(status > 0, network_exception("cannot command complete"));

	se.enable = 0x01;
	se.filter_dup = 0x01;
	rq.ogf = OGF_LE_CTL;
	rq.ocf = OCF_LE_SET_SCAN_ENABLE;
	rq.cparam = &se;
	rq.clen = LE_SET_SCAN_ENABLE_CP_SIZE;
	rq.rparam = &status;
	rq.rlen = 1;
	THR(hci_send_req(sock, &rq, 1000) < 0, network_exception("cannot hci_send_req"));
	THR(status > 0, network_exception("cannot command complete"));

	protocol::start();
}

void ble::stop() {
	protocol::stop();
	for (auto p : to_send_down_later) {
		delete p.second;
	}
	THR(close(sock) < 0, network_exception("cannot close sock"));
}

class _154 : public protocol {

private:

	int sock;

	map<BYTE8, raw_message *> to_send_down_later;

protected:

	virtual void send_once(raw_message *rmsg) override;

	virtual raw_message *recv_once() override;

public:

	virtual bool can_secure_with_C(raw_message &rmsg) const noexcept override;

	virtual bool can_secure_with_A(raw_message &rmsg) const noexcept override;

	virtual void start() override;

	virtual void stop() override;

};

void _154::send_once(raw_message *rmsg) {
	to_send_down_later.insert(make_pair(rmsg->broadcast ? BROADCAST_PLACEHOLDER : rmsg->imm_addr,
			rmsg));
}

raw_message *_154::recv_once() {
	sockaddr_ieee802154 sa;
	fd_set socks;
	int msg_len, sa_len = sizeof(sa);
	raw_message *rmsg;
	map<BYTE8, raw_message *>::iterator iter;
	int retval;

	FD_ZERO(&socks);
	FD_SET(sock, &socks);
	retval = select(sock + 1, &socks, nullptr, nullptr, nullptr);
	if (!run) {
		return nullptr;
	}
	THR(retval < 0,
			network_exception("cannot select sock"));
	THR(ioctl(sock, FIONREAD, &msg_len) < 0, network_exception("cannot ioctl sock"));
	rmsg = new raw_message(new BYTE[msg_len]);
	rmsg->TML = msg_len;
	msg_len = recvfrom(sock, rmsg->msg, msg_len, 0, reinterpret_cast<sockaddr *>(&sa),
			reinterpret_cast<socklen_t *>(&sa_len));
	THR(msg_len < 0, network_exception("cannot recvfrom sock"));
	THR(msg_len < rmsg->TML, network_exception("cannot recvfrom sock fully"));
	memcpy_endian(&rmsg->imm_addr, sa.addr.hwaddr, sizeof(rmsg->imm_addr));
	rmsg->CCF = false;
	rmsg->proto = this;
	rmsg->ACF = false;
	rmsg->TWR = my_now();
	rmsg->broadcast = true;
	rmsg->override_implicit_rules = false;

	iter = to_send_down_later.find(rmsg->imm_addr);
	if (iter != to_send_down_later.end()) {
		iter = to_send_down_later.find(BROADCAST_PLACEHOLDER);
	}
	if (iter != to_send_down_later.end()) {
		msg_len = sendto(sock, iter->second->msg, iter->second->TML, 0,
				reinterpret_cast<sockaddr *>(&sa), sizeof(sa));
		THR(msg_len < 0, network_exception("cannot send sock"));
		THR(msg_len < iter->second->TML, network_exception("cannot send sock fully"));
		delete iter->second;
		to_send_down_later.erase(iter);
	}
	return rmsg;
}

bool _154::can_secure_with_C(raw_message &) const noexcept { return false; }

bool _154::can_secure_with_A(raw_message &) const noexcept { return false; }

void _154::start() {
	sockaddr_ieee802154 sa;
	BYTE addr[8] = { 0 };

	THR(is_running(), system_exception("starting started protocol"));

	sock = socket(AF_IEEE802154, SOCK_DGRAM, 0);
	THR(sock < 0, network_exception("cannot socket sock"));
	check_sock(sock);
	sa.family = AF_IEEE802154;
	sa.addr.addr_type = IEEE802154_ADDR_LONG;
	sa.addr.pan_id = 0;
	memcpy(sa.addr.hwaddr, addr, sizeof(addr));
	THR(bind(sock, reinterpret_cast<sockaddr *>(&sa), sizeof(sa)) < 0,
			network_exception("cannot bind sock"));

	protocol::start();
}

void _154::stop() {
	protocol::stop();
	for (auto p : to_send_down_later) {
		delete p.second;
	}
	THR(close(sock) < 0, network_exception("cannot close sock"));
}

class tcp : public protocol {

private:

	/* TCP_listen_socket */
	int tcp_sock;

	/* TLS_listen_socket */
	int tls_sock;

	/* singleset of all opensock::sock-s, tcp_sock and tls_sock */
	fd_set socks;//used in multiple threads

	/* singlemap of all opensock-s, indexable by opensock::sock
	(no socket has multiple connections) */
	map<int, opensock *> sock_opensock;

	/* multimap of all opensock-s, indexable by opensock::imm_addr (same content different index) */
	multimap<BYTE8, opensock *> addr_opensock;

	/* sigmask for select */
	sigset_t sigmask;

	SSL_CTX *server_ctx;

	SSL_CTX *client_ctx;

protected:

	virtual void send_once(raw_message *rmsg) override;

	virtual raw_message *recv_once() override;

public:

	virtual bool can_secure_with_C(raw_message &rmsg) const noexcept override;

	virtual bool can_secure_with_A(raw_message &rmsg) const noexcept override;

	virtual void start() override;

	virtual void stop() override;

};

//sock = find_open_TCP_socket(imm_DST, CCF, ACF);
//if (sock == NOTHING) {
//	sock = new_TCP_socket();
//	bind(sock);
//	connect(sock, imm_DST);
//	if (ACF || CCF) {
//		sock->security_object = new_TLS_object(CCF, ACF);
//	} else {
//		sock->security_object = NOTHING;
//	}
//}
//send(sock, rmsg);
void tcp::send_once(raw_message *rmsg) {
	sockaddr_in sa;
	int new_sock, retval;
	bool security = rmsg->CCF || rmsg->ACF;
	multimap<BYTE8, opensock *>::iterator iter = addr_opensock.find(rmsg->imm_addr),
			end = addr_opensock.end();
	BIO *bio;

	THR(rmsg == nullptr, system_exception("send_once received nullptr"));
	THR(rmsg->proto != this, system_exception("send_once received other proto's message"));
	THR(rmsg->broadcast, system_exception("cannot broadcast tcp"));
	sa.sin_family = AF_INET;
	while (iter != end && iter->first == rmsg->imm_addr
			&& (iter->second->CCF != rmsg->CCF || iter->second->ACF != rmsg->ACF)) {
		iter++;
	}
	if (iter == end || iter->first != rmsg->imm_addr) {
		new_sock = socket(AF_INET, SOCK_STREAM, IPPROTO_TCP);
		THR(new_sock < 0, network_exception("cannot socket new_sock"));
		check_sock(new_sock);
		sa.sin_port = 0;
		sa.sin_addr.s_addr = INADDR_ANY;
		THR(bind(new_sock, reinterpret_cast<sockaddr *>(&sa), sizeof(sockaddr)) < 0,
				network_exception("cannot bind new_sock"));
		sa.sin_port = htons(security ? TLS_PORT : TCP_PORT);
		sa.sin_addr = BYTE8_to_ia(rmsg->imm_addr);
		THR(connect(new_sock, reinterpret_cast<sockaddr *>(&sa), sizeof(sockaddr)) < 0,
				network_exception("cannot connect new_sock"));

		iter = addr_opensock.insert(make_pair(rmsg->imm_addr, new opensock));
		iter->second->sock = new_sock;
		iter->second->imm_addr = rmsg->imm_addr;
		sock_opensock[new_sock] = iter->second;

		if (security) {
			iter->second->ssl = SSL_new(client_ctx);
			bio = BIO_new_socket(new_sock, BIO_NOCLOSE);
			SSL_set_bio(iter->second->ssl, bio, bio);
			if (!rmsg->CCF) {
				SSL_set_cipher_list(iter->second->ssl, "eNULL");
			} else if (!rmsg->ACF) {
				SSL_set_cipher_list(iter->second->ssl, "aNULL");
			} else {
				SSL_set_cipher_list(iter->second->ssl, "ALL:!aNULL");
			}
			retval = SSL_connect(iter->second->ssl);
			THR(retval < 0, network_exception("cannot SSL_connect a socket"));
			THR(retval == 0, network_exception("cannot SSL_connect a socket fully"));
			iter->second->CCF = rmsg->CCF;
			iter->second->ACF = rmsg->ACF;
		} else {
			iter->second->ssl = nullptr;
			iter->second->CCF = false;
			iter->second->ACF = false;
		}
		FD_SET(new_sock, &socks);//only now is the socket ready for receiving
		kill(getpid(), SIGUSR1);
	}
	retval = security ? SSL_write(iter->second->ssl, rmsg->msg, rmsg->TML)
			: send(iter->second->sock, rmsg->msg, rmsg->TML, 0);
	THR(retval < 0, network_exception("cannot send a socket"));
	THR(retval < rmsg->TML, network_exception("cannot send a socket fully"));
	delete rmsg;
}

//do {
//	sock = wait_for_active_TCP_socket();
//  if (sock == TCP_listen_socket || sock == TLS_listen_socket) {
//		new_sock = accept(sock);
//		if (sock == TLS_listen_socket) {
//			new_sock->security_object = TLS_accept(new_sock);
//		} else {
//			new_sock->security_object = NOTHING;
//		}
//	} else {
//		receive_one_byte(sock, temp);
//		if (temp == SHUTDOWN_REQUEST) {
//			shutdown_socket(sock);
//		} else {
//			receive_up_to_payload(sock, temp);
//			rmsg = temp;
//			receive_rest_of_message(sock, rmsg);
//			return rmsg;
//		}
//	}
//} while (true);
raw_message *tcp::recv_once() {//TCP message must contain HD and LEN
	sockaddr_in sa;
	raw_message *rmsg;
	int sock, new_sock, retval, tml, sa_len = sizeof(sockaddr);
	opensock *os;
	bool security;
	BYTE temp[20];
	header HD;
	BYTE2 LEN;
	map<int, opensock *>::iterator iter_sock;
	multimap<BYTE8, opensock *>::iterator iter_addr;
	const SSL_CIPHER *c;
	BIO *bio;
	fd_set socks = this->socks;

	sa.sin_family = AF_INET;
	do {
		sock = pselect(highest_sock + 1, &socks, nullptr, nullptr, nullptr, &sigmask);
		if (!run) {
			return nullptr;
		}
		if (sock < 0) {
			THR(errno != EINTR, network_exception("cannot select sock"));
			socks = this->socks;
			continue;
		}
		sock = -1;
		if (FD_ISSET(tcp_sock, &socks)) {
			sock = tcp_sock;
		} else if (FD_ISSET(tls_sock, &socks)) {
			sock = tls_sock;
		} else {
			for (pair<int, opensock *> p : sock_opensock) {
				if (FD_ISSET(p.first, &socks)) {
					sock = p.first;
					break;
				}
			}
		}
		THR(sock < 0, system_exception("cannot find socket"));
		if (sock == tcp_sock || sock == tls_sock) {
			new_sock = accept(sock, reinterpret_cast<sockaddr *>(&sa),
					reinterpret_cast<socklen_t *>(&sa_len));
			THR(new_sock < 0, network_exception("cannot accept new_sock"));
			check_sock(new_sock);

			os = new opensock;
			os->sock = new_sock;
			os->imm_addr = ia_to_BYTE8(sa.sin_addr);
			sock_opensock[new_sock] = os;
			addr_opensock.insert(make_pair(os->imm_addr, os));

			if (sock == tls_sock) {
				os->ssl = SSL_new(server_ctx);
				bio = BIO_new_socket(new_sock, BIO_NOCLOSE);
				SSL_set_bio(os->ssl, bio, bio);
				SSL_set_cipher_list(os->ssl, "ALL:eNULL");
				retval = SSL_accept(os->ssl);
				THR(retval < 0, network_exception("cannot SSL_accept a socket"));
				THR(retval == 0, network_exception("cannot SSL_accept a socket fully"));
				c = SSL_get_current_cipher(os->ssl);
				os->CCF = SSL_CIPHER_get_cipher_nid(c) != NID_undef;
				os->ACF = SSL_CIPHER_get_auth_nid(c) != NID_undef;
			} else {
				os->ssl = nullptr;
				os->CCF = false;
				os->ACF = false;
			}
			FD_SET(new_sock, &this->socks);
		} else {
			iter_sock = sock_opensock.find(sock);
			security = iter_sock->second->ssl != nullptr;
			retval = security ? SSL_read(iter_sock->second->ssl, temp, 1)
					: recv(sock, temp, 1, 0);
			THR(retval < 0, network_exception("cannot recv a socket"));
			if (retval == 0) {
				if (security) {
					THR(SSL_shutdown(iter_sock->second->ssl) < 0,
							network_exception("cannot SSL_shutdown a socket"));
					SSL_free(iter_sock->second->ssl);
				}
				THR(shutdown(sock, SHUT_WR) < 0, network_exception("cannot shutdown sock"));
				THR(close(sock) < 0, network_exception("cannot close sock"));
				for (iter_addr = addr_opensock.find(iter_sock->second->imm_addr);
						iter_addr->second != iter_sock->second; iter_addr++)
					;
				delete iter_sock->second;
				sock_opensock.erase(iter_sock);
				addr_opensock.erase(iter_addr);
				FD_CLR(sock, &this->socks);
			} else {
				HD.put_as_byte(*temp);
				if (HD.I) {
					retval = security ? SSL_read(iter_sock->second->ssl, temp + 1, 1)
							: recv(sock, temp + 1, 1, 0);
					THR(retval < 0, network_exception("cannot recv a socket"));
					THR(retval == 0, network_exception("cannot recv a socket fully"));
					tml = 2;
				} else {
					tml = 1;
				}
				if (HD.L) {
					retval = security ? SSL_read(iter_sock->second->ssl, temp + tml, 2)
							: recv(sock, temp + tml, 2, 0);
					THR(retval < 0, network_exception("cannot recv a socket"));
					THR(retval < 2, network_exception("cannot recv a socket fully"));
					memcpy_endian(&LEN, temp + tml, 2);
					tml += 2;
				} else {
					throw message_exception("cannot determine TML");
				}
				if (HD.D) {
					retval = security ? SSL_read(iter_sock->second->ssl, temp + tml, 8)
							: recv(sock, temp + tml, 8, 0);
					THR(retval < 0, network_exception("cannot recv a socket"));
					THR(retval < 8, network_exception("cannot recv a socket fully"));
					tml += 8;
				}
				if (HD.S) {
					retval = security ? SSL_read(iter_sock->second->ssl, temp + tml, 8)
							: recv(sock, temp + tml, 8, 0);
					THR(retval < 0, network_exception("cannot recv a socket"));
					THR(retval < 8, network_exception("cannot recv a socket fully"));
					tml += 8;
				}
				rmsg = new raw_message(new BYTE[tml + LEN + 4]);
				memcpy(rmsg->msg, temp, tml);
				if (LEN > 0) {
					retval = security ? SSL_read(iter_sock->second->ssl, rmsg->msg + tml, LEN)
							: recv(sock, rmsg->msg + tml, LEN, 0);//would block if LEN=0
					THR(retval < 0, network_exception("cannot recv a socket"));
					THR(retval < LEN, network_exception("cannot recv a socket fully"));
					tml += retval;
				}
				if (HD.R) {
					retval = security ? SSL_read(iter_sock->second->ssl, rmsg->msg + tml, 4)
							: recv(sock, rmsg->msg + tml, 4, 0);
					THR(retval < 0, network_exception("cannot recv a socket"));
					THR(retval < 4, network_exception("cannot recv a socket fully"));
					tml += 4;
				}
				rmsg->TML = tml;
				rmsg->imm_addr = iter_sock->second->imm_addr;
				rmsg->TWR = my_now();
				rmsg->CCF = iter_sock->second->CCF;
				rmsg->ACF = iter_sock->second->ACF;
				rmsg->proto = this;
				rmsg->broadcast = false;
				rmsg->override_implicit_rules = false;
				return rmsg;
			}
		}
		socks = this->socks;
	} while (true);
	throw system_exception("recv_once went through");
}

bool tcp::can_secure_with_C(raw_message &rmsg) const noexcept { return !rmsg.broadcast; }

bool tcp::can_secure_with_A(raw_message &rmsg) const noexcept { return !rmsg.broadcast; }

void tcp::start() {
	sockaddr_in sa;

	THR(is_running(), system_exception("starting started protocol"));

	FD_ZERO(&socks);
	server_ctx = SSL_CTX_new(TLS_server_method());
	SSL_CTX_set_min_proto_version(server_ctx, TLS1_2_VERSION);
	SSL_CTX_set_max_proto_version(server_ctx, TLS1_2_VERSION);
	THR(SSL_CTX_use_PrivateKey_file(server_ctx, "privateKey.pem", SSL_FILETYPE_PEM) != 1,
			system_exception("TLS server unable to use private key"));
	THR(SSL_CTX_use_certificate_file(server_ctx, "certificate.pem", SSL_FILETYPE_PEM) != 1,
			system_exception("TLS server unable to use certificate"));
	THR(SSL_CTX_check_private_key(server_ctx) != 1,
			system_exception("TLS server's private key and certificate do not match"));

	client_ctx = SSL_CTX_new(TLS_client_method());
	SSL_CTX_set_min_proto_version(client_ctx, TLS1_2_VERSION);
	SSL_CTX_set_max_proto_version(client_ctx, TLS1_2_VERSION);
	THR(SSL_CTX_use_PrivateKey_file(client_ctx, "privateKey.pem", SSL_FILETYPE_PEM) != 1,
			system_exception("TLS client unable to use private key"));
	THR(SSL_CTX_use_certificate_file(client_ctx, "certificate.pem", SSL_FILETYPE_PEM) != 1,
			system_exception("TLS client unable to use certificate"));
	THR(SSL_CTX_check_private_key(client_ctx) != 1,
			system_exception("TLS client's private key and certificate do not match"));

	tcp_sock = socket(AF_INET, SOCK_STREAM, IPPROTO_TCP);
	THR(tcp_sock < 0, network_exception("cannot socket tcp_sock"));
	check_sock(tcp_sock);
	sa.sin_family = AF_INET;
	sa.sin_port = htons(TCP_PORT);
	sa.sin_addr.s_addr = INADDR_ANY;
	THR(bind(tcp_sock, reinterpret_cast<sockaddr *>(&sa), sizeof(sockaddr)) < 0,
			network_exception("cannot bind tcp_sock"));
	THR(listen(tcp_sock, TCP_BACKLOG) < 0, network_exception("cannot listen tcp_sock"));
	FD_SET(tcp_sock, &socks);

	tls_sock = socket(AF_INET, SOCK_STREAM, IPPROTO_TCP);
	THR(tls_sock < 0, network_exception("cannot socket tls_sock"));
	check_sock(tls_sock);
	sa.sin_port = htons(TLS_PORT);
	THR(bind(tls_sock, reinterpret_cast<sockaddr *>(&sa), sizeof(sockaddr)) < 0,
			network_exception("cannot bind tls_sock"));
	THR(listen(tls_sock, TLS_BACKLOG) < 0, network_exception("cannot listen tls_sock"));
	FD_SET(tls_sock, &socks);

	sigemptyset(&sigmask);
	sigaddset(&sigmask, SIGUSR2);

	protocol::start();
}

void tcp::stop() {
	protocol::stop();
	for (pair<int, opensock *> p : sock_opensock) {
		if (p.second->ssl != nullptr) {
			THR(SSL_shutdown(p.second->ssl) < 0, network_exception("cannot SSL_shutdown a socket"));
			SSL_free(p.second->ssl);
		}
		THR(shutdown(p.first, SHUT_WR) < 0, network_exception("cannot shutdown a socket"));
		THR(close(p.first) < 0, network_exception("cannot close a socket"));
		delete p.second;
	}

	THR(shutdown(tls_sock, SHUT_WR) < 0, network_exception("cannot SSL_shutdown tls_sock"));
	THR(close(tls_sock) < 0, network_exception("cannot close tls_sock"));

	THR(shutdown(tcp_sock, SHUT_WR) < 0, network_exception("cannot shutdown tcp_sock"));
	THR(close(tcp_sock) < 0, network_exception("cannot close tcp_sock"));

	SSL_CTX_free(client_ctx);
	SSL_CTX_free(server_ctx);
}

#define MAX_DEVICE_INDEX 10

#define UDP_PORT 60000

#define DTLS_PORT 60001

#define PRLIMIT 16777216

class udp : public protocol {

private:

	/* UDP_listen_socket */
	int udp_sock;

	/* DTLS_listen_socket */
	int dtls_sock;

	/* singleset of all opensock::sock-s, udp_sock and dtls_sock */
	fd_set socks;//used in multiple threads

	/* multimap of all opensock-s, indexable by opensock::sock
	(multiple connections on dtls_sock only) */
	multimap<int, opensock *> sock_opensock;

	/* multimap of all opensock-s, indexable by opensock::imm_addr (same content different index) */
	multimap<BYTE8, opensock *> addr_opensock;

	/* sigmask for select */
	sigset_t sigmask;

	SSL_CTX *server_ctx;

	SSL_CTX *client_ctx;

	int broadcast_sock;

protected:

	virtual void send_once(raw_message *rmsg) override;

	virtual raw_message *recv_once() override;

public:

	virtual bool can_secure_with_C(raw_message &rmsg) const noexcept override;

	virtual bool can_secure_with_A(raw_message &rmsg) const noexcept override;

	virtual void start() override;

	virtual void stop() override;

};

//sock = find_open_UDP_socket(imm_DST, CCF, ACF);
//if (sock == NOTHING) {
//	sock = new_UDP_socket();
//	bind(sock);
//	connect(sock, imm_DST);
//	if (ACF || CCF) {
//		sock->security_object = new_DTLS_object(CCF, ACF);
//	} else {
//		sock->security_object = NOTHING;
//	}
//}
//send(sock, rmsg);
void udp::send_once(raw_message *rmsg) {
	sockaddr_in sa;
	int new_sock, retval;
	bool security = rmsg->CCF || rmsg->ACF;
	multimap<BYTE8, opensock *>::iterator iter = addr_opensock.find(rmsg->imm_addr),
			end = addr_opensock.end();
	BIO *bio;

	THR(rmsg == nullptr, system_exception("send_once received nullptr"));
	THR(rmsg->proto != this, system_exception("send_once received other proto's message"));
	if (rmsg->broadcast) {
		retval = send(broadcast_sock, rmsg->msg, rmsg->TML, 0);
		THR(retval < 0, network_exception("cannot broadcast socket"));
		THR(retval < rmsg->TML, network_exception("cannot broadcast socket fully"));
		delete rmsg;
		return;
	}
	sa.sin_family = AF_INET;
	while (iter != end && iter->first == rmsg->imm_addr
			&& (iter->second->CCF != rmsg->CCF || iter->second->ACF != rmsg->ACF)) {
		iter++;
	}
	if (iter == end || iter->first != rmsg->imm_addr) {
		new_sock = socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP);
		THR(new_sock < 0, network_exception("cannot socket new_sock"));
		check_sock(new_sock);
		sa.sin_port = 0;
		sa.sin_addr.s_addr = INADDR_ANY;
		THR(bind(new_sock, reinterpret_cast<sockaddr *>(&sa), sizeof(sockaddr)) < 0,
				network_exception("cannot bind new_sock"));
		sa.sin_port = htons(security ? DTLS_PORT : UDP_PORT);
		sa.sin_addr = BYTE8_to_ia(rmsg->imm_addr);
		THR(connect(new_sock, reinterpret_cast<sockaddr *>(&sa), sizeof(sockaddr)) < 0,
				network_exception("cannot connect new_sock"));

		iter = addr_opensock.insert(make_pair(rmsg->imm_addr, new opensock));
		iter->second->sock = new_sock;
		iter->second->imm_addr = rmsg->imm_addr;
		sock_opensock.insert(make_pair(new_sock, iter->second));

		if (security) {
			iter->second->ssl = SSL_new(client_ctx);
			bio = BIO_new_dgram(new_sock, BIO_NOCLOSE);
			BIO_ctrl(bio, BIO_CTRL_DGRAM_SET_CONNECTED, 0, &sa);
			SSL_set_bio(iter->second->ssl, bio, bio);
			if (!rmsg->CCF) {
				SSL_set_cipher_list(iter->second->ssl, "eNULL");
			} else if (!rmsg->ACF) {
				SSL_set_cipher_list(iter->second->ssl, "aNULL");
			} else {
				SSL_set_cipher_list(iter->second->ssl, "ALL:!aNULL");
			}
			retval = SSL_connect(iter->second->ssl);
			THR(retval < 0, network_exception("cannot SSL_connect a socket"));
			THR(retval == 0, network_exception("cannot SSL_connect a socket fully"));
			iter->second->CCF = rmsg->CCF;
			iter->second->ACF = rmsg->ACF;
		} else {
			iter->second->ssl = nullptr;
			iter->second->CCF = false;
			iter->second->ACF = false;
		}
		FD_SET(new_sock, &socks);//only now is the socket ready for receiving
		kill(getpid(), SIGUSR2);
	}
	retval = security ? SSL_write(iter->second->ssl, rmsg->msg, rmsg->TML)
			: send(iter->second->sock, rmsg->msg, rmsg->TML, 0);
	THR(retval < 0, network_exception("cannot send a socket"));
	THR(retval < rmsg->TML, network_exception("cannot send a socket fully"));
	delete rmsg;
}

//do {
//	sock = wait_for_active_UDP_socket();
//	if (sock == UDP_listen_socket || sock == DTLS_listen_socket) {
//		receive_bytes_without_DTLS(sock, temp);
//		if (sock == DTLS_listen_socket) {
//			if (unconnected(imm_SRC, sock)) {
//				sock->security_object[imm_SRC] = new_DTLS_object(sock);
//			}
//			msg = process_bytes_with_DTLS(sock, temp);
//			if (msg == NOTHING) {
//				continue;
//			}
//		}
//	} else {
//		receive(sock, temp);
//	}
//	rmsg = temp;
//	return rmsg;
//} while (true);
raw_message *udp::recv_once() {//UDP message can be empty
	sockaddr_in sa;
	raw_message *rmsg;
	int sock, msg_len, sa_len = sizeof(sockaddr), retval;
	opensock *os;
	BYTE *temp;
	multimap<int, opensock *>::iterator iter, end;
	BYTE8 addr;
	const SSL_CIPHER *c;
	fd_set socks = this->socks;

	sa.sin_family = AF_INET;
	do {
		sock = pselect(highest_sock + 1, &socks, nullptr, nullptr, nullptr, &sigmask);
		if (!run) {
			return nullptr;
		}
		if (sock < 0) {
			THR(errno != EINTR, network_exception("cannot select sock"));
			socks = this->socks;
			continue;
		}
		if (FD_ISSET(broadcast_sock, &socks)) {
			THR(ioctl(sock, FIONREAD, &msg_len) < 0, network_exception("cannot ioctl sock"));
			temp = new BYTE[msg_len];
			retval = recvfrom(sock, temp, msg_len, 0, reinterpret_cast<sockaddr *>(&sa),
					reinterpret_cast<socklen_t *>(&sa_len));
			THR(retval < 0, network_exception("cannot recvfrom sock"));
			THR(retval < msg_len, network_exception("cannot recvfrom sock fully"));
			rmsg = new raw_message(temp);
			rmsg->TML = msg_len;
			rmsg->imm_addr = ia_to_BYTE8(sa.sin_addr);
			rmsg->CCF = false;
			rmsg->ACF = false;
			rmsg->TWR = my_now();
			rmsg->proto = this;
			rmsg->broadcast = true;
			rmsg->override_implicit_rules = true;
			return rmsg;
		}
		sock = -1;
		if (FD_ISSET(udp_sock, &socks)) {
			sock = udp_sock;
		} else if (FD_ISSET(dtls_sock, &socks)) {
			sock = dtls_sock;
		} else {
			for (pair<int, opensock *> p : sock_opensock) {
				if (FD_ISSET(p.first, &socks)) {
					sock = p.first;
					break;
				}
			}
		}
		THR(sock < 0, system_exception("cannot find socket"));
		if (sock == udp_sock || sock == dtls_sock) {
			THR(ioctl(sock, FIONREAD, &msg_len) < 0,
					network_exception("cannot ioctl sock"));//also works for empty datagrams
			temp = new BYTE[msg_len];
			retval = recvfrom(sock, temp, msg_len, 0, reinterpret_cast<sockaddr *>(&sa),
					reinterpret_cast<socklen_t *>(&sa_len));
			THR(retval < 0, network_exception("cannot recvfrom sock"));
			THR(retval < msg_len, network_exception("cannot recvfrom sock fully"));
			if (sock == dtls_sock) {
				for (iter = sock_opensock.find(sock), end = sock_opensock.end(),
						addr = ia_to_BYTE8(sa.sin_addr); iter != end
						&& iter->first == sock && iter->second->imm_addr != addr; iter++)
					;
				if (iter == end || iter->first != sock) {
					os = new opensock;
					os->sock = sock;
					os->imm_addr = addr;
					os->ssl = SSL_new(server_ctx);
					iter = sock_opensock.insert(make_pair(sock, os));
					addr_opensock.insert(make_pair(addr, os));
					SSL_set_bio(os->ssl, BIO_new(BIO_s_mem()), BIO_new_dgram(sock, BIO_NOCLOSE));
					BIO_ctrl(SSL_get_wbio(os->ssl), BIO_CTRL_DGRAM_CONNECT, 0, &sa);
					BIO_set_mem_eof_return(SSL_get_rbio(os->ssl), -1);
					SSL_set_accept_state(os->ssl);
					SSL_set_cipher_list(os->ssl, "ALL:eNULL");
				}
				retval = BIO_write(SSL_get_rbio(iter->second->ssl), temp, msg_len);
				THR(retval < 0, network_exception("cannot BIO_write to a socket"));
				THR(retval < msg_len, network_exception("cannot BIO_write to a socket fully"));
				msg_len = SSL_read(iter->second->ssl, temp, msg_len);//if temp written to mem_rBIO
															//was a control message this will also
															//write potential response to dgram_wBIO
				if (msg_len < 0) {//mem_rBIO will not block
					THR(SSL_give_error(iter->second->ssl, msg_len) != "WANT_READ",
							network_exception("unexpected error"));
					delete[] temp;
					socks = this->socks;
					continue;
				}
				c = SSL_get_current_cipher(iter->second->ssl);
				iter->second->CCF = SSL_CIPHER_get_cipher_nid(c) != NID_undef;
				iter->second->ACF = SSL_CIPHER_get_auth_nid(c) != NID_undef;
			}
		} else {
			iter = sock_opensock.find(sock);
			if (iter->second->ssl != nullptr) {
				msg_len = SSL_pending(iter->second->ssl);
				temp = new BYTE[msg_len];
				retval = SSL_read(iter->second->ssl, temp, msg_len);
			} else {
				THR(ioctl(sock, FIONREAD, &msg_len) < 0, network_exception("cannot ioctl sock"));
				temp = new BYTE[msg_len];
				retval = recv(sock, temp, msg_len, 0);
			}
			THR(retval < 0, network_exception("cannot recv a socket"));
			THR(retval < msg_len, network_exception("cannot recv a socket fully"));
			sa.sin_addr = BYTE8_to_ia(iter->second->imm_addr);
		}
		rmsg = new raw_message(temp);
		rmsg->TML = msg_len;
		rmsg->imm_addr = ia_to_BYTE8(sa.sin_addr);
		rmsg->CCF = sock == udp_sock ? false : iter->second->CCF;
		rmsg->ACF = sock == udp_sock ? false : iter->second->ACF;
		rmsg->TWR = my_now();
		rmsg->proto = this;
		return rmsg;
	} while (true);
	throw system_exception("recv_once went through");
}

bool udp::can_secure_with_C(raw_message &rmsg) const noexcept { return !rmsg.broadcast; }

bool udp::can_secure_with_A(raw_message &rmsg) const noexcept { return !rmsg.broadcast; }

void udp::start() {
	sockaddr_in sa;
	int a = 1;

	THR(is_running(), system_exception("starting started protocol"));

	FD_ZERO(&socks);
	server_ctx = SSL_CTX_new(DTLS_server_method());
	SSL_CTX_set_min_proto_version(server_ctx, DTLS1_2_VERSION);
	SSL_CTX_set_max_proto_version(server_ctx, DTLS1_2_VERSION);
	THR(SSL_CTX_use_PrivateKey_file(server_ctx, "privateKey.pem", SSL_FILETYPE_PEM) != 1,
			system_exception("DTLS server unable to use private key"));
	THR(SSL_CTX_use_certificate_file(server_ctx, "certificate.pem", SSL_FILETYPE_PEM) != 1,
			system_exception("DTLS server unable to use certificate"));
	THR(SSL_CTX_check_private_key(server_ctx) != 1,
			system_exception("DTLS server's private key and certificate do not match"));

	client_ctx = SSL_CTX_new(DTLS_client_method());
	SSL_CTX_set_min_proto_version(client_ctx, DTLS1_2_VERSION);
	SSL_CTX_set_max_proto_version(client_ctx, DTLS1_2_VERSION);
	THR(SSL_CTX_use_PrivateKey_file(client_ctx, "privateKey.pem", SSL_FILETYPE_PEM) != 1,
			system_exception("DTLS client unable to use private key"));
	THR(SSL_CTX_use_certificate_file(client_ctx, "certificate.pem", SSL_FILETYPE_PEM) != 1,
			system_exception("DLTS client unable to use certificate"));
	THR(SSL_CTX_check_private_key(client_ctx) != 1,
			system_exception("DTLS client's private key and certificate do not match"));

	udp_sock = socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP);
	THR(udp_sock < 0, network_exception("cannot socket udp_sock"));
	check_sock(udp_sock);
	sa.sin_family = AF_INET;
	sa.sin_port = htons(UDP_PORT);
	sa.sin_addr.s_addr = INADDR_ANY;
	THR(setsockopt(udp_sock, SOL_SOCKET, SO_REUSEPORT, &a, sizeof(a)) < 0,
			network_exception("cannot SO_REUSEPORT udp_sock"));
	THR(bind(udp_sock, reinterpret_cast<sockaddr *>(&sa), sizeof(sockaddr)) < 0,
			network_exception("cannot bind udp_sock"));
	FD_SET(udp_sock, &socks);

	dtls_sock = socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP);
	THR(dtls_sock < 0, network_exception("cannot socket dtls_sock"));
	check_sock(dtls_sock);
	sa.sin_port = htons(DTLS_PORT);
	THR(bind(dtls_sock, reinterpret_cast<sockaddr *>(&sa), sizeof(sockaddr)) < 0,
			network_exception("cannot bind dtls_sock"));
	FD_SET(dtls_sock, &socks);

	broadcast_sock = socket(AF_INET, SOCK_DGRAM, IPPROTO_UDP);
	THR(broadcast_sock < 0, network_exception("cannot socket broadcast_sock"));
	check_sock(broadcast_sock);
	THR(setsockopt(broadcast_sock, SOL_SOCKET, SO_BROADCAST, &a, sizeof(a)) < 0,
			network_exception("cannot setsockopt broadcast_sock"));
	sa.sin_port = htons(UDP_PORT);
	sa.sin_addr.s_addr = INADDR_BROADCAST;
	THR(setsockopt(broadcast_sock, SOL_SOCKET, SO_REUSEPORT, &a, sizeof(a)) < 0,
			network_exception("cannot SO_REUSEPORT broadcast_sock"));
	THR(bind(broadcast_sock, reinterpret_cast<sockaddr *>(&sa), sizeof(sockaddr)) < 0,
			network_exception("cannot bind broadcast_sock"));
	FD_SET(broadcast_sock, &socks);

	sigemptyset(&sigmask);
	sigaddset(&sigmask, SIGUSR1);

	protocol::start();
}

void udp::stop() {
	protocol::stop();
	for (pair<int, opensock *> p : sock_opensock) {
		if (p.second->ssl != nullptr) {
			SSL_free(p.second->ssl);
		}
		if (p.first != dtls_sock) {
			THR(close(p.first) < 0, network_exception("cannot close a socket"));
		}
		delete p.second;
	}

	THR(close(broadcast_sock) < 0, network_exception("cannot close broadcast_sock"));

	THR(close(dtls_sock) < 0, network_exception("cannot close dtls_sock"));

	THR(close(udp_sock) < 0, network_exception("cannot close udp_sock"));

	SSL_CTX_free(client_ctx);
	SSL_CTX_free(server_ctx);
}

/*
 * C++11/14:
 * nullptr
 * basic_string::front(), basic_string::back()
 * <regex>
 * enum struct, enum : ?
 * basic_string::pop_back()
 * 0b, digit separation
 * auto for
 * >>
 * map::cend()
 * basic_string::cbegin(), basic_string::cend()
 * override
 * = delete
 * noexcept
 * using member functions
 * __VA_ARGS__
 * <chrono>
 * <random>
 * <cstdint>
 * <thread>
 * auto vars
 * static_assert
 * put_time, get_time
 * us, s
 * atomic_int, atomic_bool
 * unique_ptr
 * append streams, append ostreams
 * suffix strings
 */
int main(int argc, char *argv[]) {
	ostringstream oss(oss.out | oss.ate);
	PGresult *res;
	int i, sock = socket(AF_UNIX, SOCK_SEQPACKET, 0);
	struct sigaction sa;
	char cwd[PATH_MAX];
	ifreq ifr;
	hci_dev_info hdi;

	conn = PQconnectdb("dbname=postgres user=postgres client_encoding=UTF8");
			//must be run with pg_ctl -D <loc> initdb -o "-E UTF8"
	THR(PQstatus(conn) != CONNECTION_OK, database_exception("error in connection"));
	sa.sa_handler = sig_ign;
	sigemptyset(&sa.sa_mask);
	sa.sa_flags = 0;
	sigaction(SIGUSR1, &sa, nullptr);
	sigaction(SIGUSR2, &sa, nullptr);
	sigaddset(&sa.sa_mask, SIGUSR1);
	sigaddset(&sa.sa_mask, SIGUSR2);
	sigprocmask(SIG_BLOCK, &sa.sa_mask, nullptr);
	getcwd(cwd, PATH_MAX);
	initialize_vars();

	PQclear(execcheckreturn("CREATE TABLE IF NOT EXISTS users(username TEXT, "
			"password TEXT NOT NULL, is_administrator BOOLEAN NOT NULL, "
			"can_view_tables BOOLEAN NOT NULL, can_send_messages BOOLEAN NOT NULL, "
			"can_inject_messages BOOLEAN NOT NULL, can_send_queries BOOLEAN NOT NULL, "
			"can_view_rules BOOLEAN NOT NULL, can_view_configuration BOOLEAN NOT NULL, "
			"can_view_permissions BOOLEAN NOT NULL, can_view_remotes BOOLEAN NOT NULL, "
			"can_execute_rules BOOLEAN NOT NULL, can_actually_login BOOLEAN NOT NULL, "
			"PRIMARY KEY(username))"));
	PQclear(execcheckreturn("CREATE TABLE IF NOT EXISTS rules(username TEXT, id INTEGER, "
			"send_receive_seconds SMALLINT NOT NULL, filter TEXT, "
			"drop_modify_nothing SMALLINT NOT NULL, modification TEXT, "
			"query_command_nothing SMALLINT NOT NULL, query_command_1 TEXT, "
			"send_inject_query_command_nothing SMALLINT NOT NULL, query_command_2 TEXT, "
			"proto_id TEXT, imm_addr BYTEA, CCF BOOLEAN, ACF BOOLEAN, broadcast BOOLEAN, "
			"override_implicit_rules BOOLEAN, activate INTEGER, deactivate INTEGER, "
			"is_active BOOLEAN NOT NULL, last_run TIMESTAMP(0) WITH TIME ZONE, "
			"run_period INTERVAL SECOND(0), next_run BIGINT, PRIMARY KEY(username, id), "
			"FOREIGN KEY(username) REFERENCES users(username) "
			"ON DELETE CASCADE ON UPDATE CASCADE)"));
	PQclear(execcheckreturn("CREATE TABLE IF NOT EXISTS addr_oID(addr BYTEA, "
			"out_ID SMALLINT NOT NULL, PRIMARY KEY(addr))"));
	PQclear(execcheckreturn("CREATE TABLE IF NOT EXISTS SRC_DST(SRC BYTEA, DST BYTEA, "
			"PRIMARY KEY(SRC, DST), FOREIGN KEY(SRC) REFERENCES addr_oID(addr) "
			"ON DELETE CASCADE ON UPDATE CASCADE)"));
	PQclear(execcheckreturn("CREATE TABLE IF NOT EXISTS ID_TWR(SRC BYTEA, DST BYTEA, ID SMALLINT, "
			"TWR TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY(SRC, DST, ID), "
			"FOREIGN KEY(SRC, DST) REFERENCES SRC_DST(SRC, DST) "
			"ON DELETE CASCADE ON UPDATE CASCADE)"));
	PQclear(execcheckreturn("CREATE TABLE IF NOT EXISTS t"s + BYTE8_to_c17charp(local_addr)
			+ "(t TIMESTAMP(4) WITHOUT TIME ZONE, PRIMARY KEY(t))"));

	res = execcheckreturn("SELECT TRUE FROM pg_class WHERE relname = \'proto_name\'");
	if (PQntuples(res) == 0) {
		PQclear(execcheckreturn("CREATE TABLE proto_name(proto TEXT, name TEXT NOT NULL, "
				"PRIMARY KEY(proto))"));
		protocols.push_back(new tcp);
		protocols.push_back(new udp);
	} else {
		instantiate_protocol_if_enabled<tcp>();
		instantiate_protocol_if_enabled<udp>();
		instantiate_protocol_if_enabled<ble>();
		instantiate_protocol_if_enabled<_154>();
	}
	PQclear(res);
	PQclear(execcheckreturn("TRUNCATE TABLE proto_name CASCADE"));//CASCADE needed for table fmforsr
	oss.str("INSERT INTO proto_name(proto, name) VALUES(");
	for (const protocol *p : protocols) {
		oss << '\'' << p->get_my_id() << "\', \'" << get_typename(typeid(*p)) << "\'), (";
	}
	oss.seekp(-3, oss.end) << '\0';
	PQclear(execcheckreturn(oss.str()));

	populate_local_proto_iaddr();
	PQclear(execcheckreturn("CREATE TABLE IF NOT EXISTS formatted_message_for_send_receive("
			"HD BYTEA, ID BYTEA, LEN BYTEA, DST BYTEA, SRC BYTEA, PL BYTEA, CRC BYTEA, "
			"ENCRYPTED BOOLEAN, SIGNED BOOLEAN, BROADCAST BOOLEAN, OVERRIDE BOOLEAN, proto TEXT, "
			"imm_addr BYTEA, CCF BOOLEAN, ACF BOOLEAN, "
			"FOREIGN KEY(proto) REFERENCES proto_name(proto))"));
	PQclear(execcheckreturn("TRUNCATE TABLE formatted_message_for_send_receive"));

	res = execcheckreturn("SELECT TRUE FROM pg_class WHERE relname = \'adapter_name\'");
	if (PQntuples(res) == 0) {
		PQclear(execcheckreturn("CREATE TABLE adapter_name(adapter INTEGER, name TEXT NOT NULL, "
				"PRIMARY KEY(adapter))"));
		oss.str("INSERT INTO adapter_name(adapter, name) VALUES(");
		i = MAX_DEVICE_INDEX;
		while (find_next_lower_device(sock, ifr, i) >= 0) {
			THR(ioctl(sock, SIOCGIFFLAGS, &ifr) < 0, system_exception("cannot SIOCGIFFLAGS ioctl"));
			if ((ifr.ifr_flags & IFF_UP) == 0) {
				ifr.ifr_flags |= IFF_UP;
				THR(ioctl(sock, SIOCSIFFLAGS, &ifr) < 0,
						system_exception("cannot SIOCSIFFLAGS ioctl"));//privileged operation!!!
				LOG_CPP("turned on device " << ifr.ifr_name << endl);
			}
			oss << i << ", \'" << ifr.ifr_name << "\'), (";
		}
		close(sock);
		sock = socket(AF_BLUETOOTH, SOCK_RAW, BTPROTO_HCI);
		hdi.dev_id = 0;
		THR(ioctl(sock, HCIGETDEVINFO, &hdi) < 0, system_exception("cannot HCIGETDEVINFO ioctl"));
		if (!hci_test_bit(HCI_UP, &hdi.flags)) {
			THR(ioctl(sock, HCIDEVUP, 0) < 0, system_exception("cannot HCIDEVUP ioctl"));
					//privileged operation!!!
			LOG_CPP("turned on device " << hdi.name << endl);
		}
		oss << MAX_DEVICE_INDEX << ", \'" << hdi.name << "\'), (";
		oss.seekp(-3, oss.end) << '\0';
		PQclear(execcheckreturn(oss.str()));
	} else {
		i = MAX_DEVICE_INDEX;
		while (find_next_lower_device(sock, ifr, i) >= 0) {
			THR(ioctl(sock, SIOCGIFFLAGS, &ifr) < 0, system_exception("cannot SIOCGIFFLAGS ioctl"));
			PQclear(res);
			res = execcheckreturn("SELECT TRUE FROM adapter_name WHERE name = \'"s + ifr.ifr_name
					+ '\'');
			if (PQntuples(res) == 0) {
				if ((ifr.ifr_flags & IFF_UP) != 0) {
					ifr.ifr_flags &= ~IFF_UP;
					THR(ioctl(sock, SIOCSIFFLAGS, &ifr) < 0,
							system_exception("cannot SIOCSIFFLAGS ioctl"));//privileged operation!!!
					LOG_CPP("turned off device " << ifr.ifr_name << endl);
				}
			} else if ((ifr.ifr_flags & IFF_UP) == 0) {
				ifr.ifr_flags |= IFF_UP;
				THR(ioctl(sock, SIOCSIFFLAGS, &ifr) < 0,
						system_exception("cannot SIOCSIFFLAGS ioctl"));//privileged operation!!!
				LOG_CPP("turned on device " << ifr.ifr_name << endl);
			}
		}
		PQclear(res);
		close(sock);
		sock = socket(AF_BLUETOOTH, SOCK_RAW, BTPROTO_HCI);
		oss.str("SELECT TRUE FROM adapter_name WHERE name = \'");
		hdi.dev_id = 0;
		THR(ioctl(sock, HCIGETDEVINFO, &hdi) < 0, system_exception("cannot HCIGETDEVINFO ioctl"));
		oss << hdi.name;
		res = execcheckreturn(oss.str() + '\'');
		if (PQntuples(res) == 0) {
			if (!hci_test_bit(HCI_UP, &hdi.flags)) {
				THR(ioctl(sock, HCIDEVUP, 0) < 0, system_exception("cannot HCIDEVUP ioctl"));
						//privileged operation!!!
				LOG_CPP("turned on device " << hdi.name << endl);
			}
		} else if (hci_test_bit(HCI_UP, &hdi.flags)) {
			THR(ioctl(sock, HCIDEVDOWN, 0) < 0, system_exception("cannot HCIDEVDOWN ioctl"));
					//privileged operation!!!
			LOG_CPP("turned off device " << hdi.name << endl);
		}
	}
	for (protocol *p : protocols) {
		p->start();
	}
	PQclear(res);
	close(sock);

	PQclear(execcheckreturn("CREATE TABLE IF NOT EXISTS SRC_proto(SRC BYTEA, proto TEXT, "
			"PRIMARY KEY(SRC, proto), FOREIGN KEY(SRC) REFERENCES addr_oID(addr) "
			"ON DELETE CASCADE ON UPDATE CASCADE, FOREIGN KEY(proto) REFERENCES proto_name(proto) "
			"ON DELETE CASCADE ON UPDATE CASCADE)"));
	PQclear(execcheckreturn("CREATE TABLE IF NOT EXISTS iSRC_TWR(SRC BYTEA, proto TEXT, "
			"imm_SRC BYTEA, TWR TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, "
			"PRIMARY KEY(SRC, proto, imm_SRC), FOREIGN KEY(SRC, proto) "
			"REFERENCES SRC_proto(SRC, proto) ON DELETE CASCADE ON UPDATE CASCADE)"));
	PQclear(execcheckreturn("CREATE TABLE IF NOT EXISTS configuration(username TEXT, "
			"forward_messages BOOLEAN NOT NULL, use_internet_switch_algorithm BOOLEAN NOT NULL, "
			"nsecs_id INTEGER NOT NULL, nsecs_src INTEGER NOT NULL, "
			"trust_everyone BOOLEAN NOT NULL, default_gateway BYTEA NOT NULL, "
			"PRIMARY KEY(username), FOREIGN KEY(username) REFERENCES users(username) "
			"ON DELETE CASCADE ON UPDATE CASCADE)"));
	PQclear(execcheckreturn("CREATE TABLE IF NOT EXISTS current_username(current_username TEXT)"));
	PQclear(execcheckreturn("TRUNCATE TABLE current_username"));
	PQclear(execcheckreturn("DROP PROCEDURE IF EXISTS ext(addr_id TEXT) CASCADE"));
	PQclear(execcheckreturn("DROP PROCEDURE IF EXISTS send_inject(send BOOLEAN, message BYTEA, "
			"proto_id TEXT, imm_addr BYTEA, CCF BOOLEAN, ACF BOOLEAN, broadcast BOOLEAN, "
			"override_implicit_rules BOOLEAN)"));
	PQclear(execcheckreturn("DROP PROCEDURE IF EXISTS load_store(load BOOLEAN)"));
	PQclear(execcheckreturn("DROP PROCEDURE IF EXISTS config()"));
	PQclear(execcheckreturn("DROP FUNCTION IF EXISTS refresh_next_timed_rule_time("
			"next_timed_rule BIGINT)"));
	PQclear(execcheckreturn("DROP PROCEDURE IF EXISTS update_permissions()"));
	PQclear(execcheckreturn("DROP PROCEDURE IF EXISTS manually_execute_timed_rule(username TEXT, "
			"id INTEGER)"));
	PQclear(execcheckreturn("DROP FUNCTION IF EXISTS current_username() CASCADE"));
	PQclear(execcheckreturn("CREATE TABLE IF NOT EXISTS raw_message_for_query_command("
			"message BYTEA)"));
	PQclear(execcheckreturn("TRUNCATE TABLE raw_message_for_query_command"));
	PQclear(execcheckreturn("CREATE TABLE IF NOT EXISTS tables(tablename NAME PRIMARY KEY)"));
	PQclear(execcheckreturn("DELETE FROM tables WHERE tablename IN (SELECT tablename FROM tables "
			"EXCEPT SELECT tablename FROM tables)"));
	PQclear(execcheckreturn("INSERT INTO tables SELECT relname FROM pg_class "
			"EXCEPT SELECT relname FROM pg_class"));
	PQclear(execcheckreturn("CREATE TABLE IF NOT EXISTS table_user(tablename NAME, username TEXT, "
			"PRIMARY KEY(tablename), FOREIGN KEY(tablename) REFERENCES tables(tablename) "
			"ON UPDATE CASCADE ON DELETE CASCADE, FOREIGN KEY(username) REFERENCES users(username) "
			"ON UPDATE CASCADE ON DELETE CASCADE)"));
	PQclear(execcheckreturn("CREATE PROCEDURE ext(addr_id TEXT) AS \'"s + cwd
			+ "/libIoT\', \'ext\' LANGUAGE C"));
	PQclear(execcheckreturn("CREATE PROCEDURE send_inject(send BOOLEAN, message BYTEA, "
			"proto_id TEXT, imm_addr BYTEA, CCF BOOLEAN, ACF BOOLEAN, broadcast BOOLEAN, "
			"override_implicit_rules BOOLEAN) AS \'"s + cwd
			+ "/libIoT\', \'send_inject\' LANGUAGE C"));
	PQclear(execcheckreturn("CREATE PROCEDURE load_store(load BOOLEAN) AS \'"s + cwd
			+ "/libIoT\', \'load_store\' LANGUAGE C"));
	PQclear(execcheckreturn("CREATE PROCEDURE config() AS \'"s + cwd
			+ "/libIoT\', \'config\' LANGUAGE C"));
	PQclear(execcheckreturn("CREATE FUNCTION refresh_next_timed_rule_time(next_timed_rule BIGINT) "
			"RETURNS void AS \'"s + cwd + "/libIoT\', \'refresh_next_timed_rule_time\' "
			"LANGUAGE C"));
	PQclear(execcheckreturn("CREATE PROCEDURE update_permissions() AS \'"s + cwd
			+ "/libIoT\', \'update_permissions\' LANGUAGE C"));
	PQclear(execcheckreturn("CREATE PROCEDURE manually_execute_timed_rule(username TEXT, "
			"id INTEGER) AS\'"s + cwd + "/libIoT\', \'manually_execute_timed_rule\' "
			"LANGUAGE C"));
	PQclear(execcheckreturn("DROP FUNCTION IF EXISTS current_username_update() CASCADE"));
	PQclear(execcheckreturn("DROP FUNCTION IF EXISTS current_username_delete() CASCADE"));
	PQclear(execcheckreturn("DROP FUNCTION IF EXISTS current_username_insert() CASCADE"));
	/*
	 * in SQL standard only RETURNS NULL ON NULL INPUT function specifier exists
	 *
	 * also, other function specifiers exist in standard SQL
	 */
	PQclear(execcheckreturn("CREATE FUNCTION current_username_update() RETURNS trigger AS \'BEGIN "
			"IF EXISTS (TABLE current_username) "
			"AND ((SELECT current_username FROM current_username) <> NEW.username "
			"OR OLD.username <> NEW.username) AND (OLD.is_administrator OR NEW.is_administrator "
			"OR (SELECT NOT users.is_administrator FROM users INNER JOIN current_username "
			"ON users.username = current_username.current_username)) THEN RETURN NULL; "
			"ELSE RETURN NEW; END IF; END;\' LANGUAGE PLPGSQL"));
	PQclear(execcheckreturn("CREATE TRIGGER current_username_update BEFORE UPDATE ON users "
			"FOR ROW EXECUTE PROCEDURE current_username_update()"));
	PQclear(execcheckreturn("CREATE FUNCTION current_username_delete() RETURNS trigger AS \'BEGIN "
			"IF EXISTS (TABLE current_username) AND (OLD.is_administrator "
			"OR (SELECT NOT users.is_administrator FROM users "
			"INNER JOIN current_username ON users.username = current_username.current_username)) "
			"THEN RETURN NULL; ELSE RETURN OLD; END IF; END;\' LANGUAGE PLPGSQL"));
	PQclear(execcheckreturn("CREATE TRIGGER current_username_delete BEFORE DELETE ON users "
			"FOR ROW EXECUTE PROCEDURE current_username_delete()"));
	PQclear(execcheckreturn("CREATE FUNCTION current_username_insert() RETURNS trigger AS \'BEGIN "
			"IF EXISTS (TABLE current_username) AND (NEW.is_administrator "
			"OR (SELECT NOT users.is_administrator FROM users "
			"INNER JOIN current_username ON users.username = current_username.current_username)) "
			"THEN RETURN NULL; ELSE RETURN NEW; END IF; END;\' LANGUAGE PLPGSQL"));
	PQclear(execcheckreturn("CREATE TRIGGER current_username_insert BEFORE INSERT ON users "
			"FOR ROW EXECUTE PROCEDURE current_username_insert()"));
	PQclear(execcheckreturn("DROP FUNCTION IF EXISTS insert_timer() CASCADE"));
	PQclear(execcheckreturn("CREATE FUNCTION insert_timer() RETURNS trigger AS \'DECLARE "
			"lastrun TIMESTAMP(0) WITH TIME ZONE; runperiod INTERVAL SECOND(0); BEGIN "
			"lastrun = CURRENT_TIMESTAMP(0); "
			"runperiod = CAST(NEW.filter AS INTEGER) * INTERVAL \'\'0:0:1\'\'; "
			"UPDATE rules SET (last_run, run_period, next_run) = (lastrun, "
			"runperiod, CAST(EXTRACT(EPOCH FROM lastrun + runperiod) AS BIGINT)) "
			"WHERE id = NEW.id AND username = NEW.username; "
			"PERFORM refresh_next_timed_rule_time((SELECT MIN(next_run) FROM rules)); "
			"RETURN NULL; END;\' LANGUAGE PLPGSQL"));
	PQclear(execcheckreturn("CREATE TRIGGER insert_timer AFTER INSERT ON rules FOR ROW "
			"WHEN (NEW.send_receive_seconds = 2 AND NEW.is_active) "
			"EXECUTE PROCEDURE insert_timer()"));
	PQclear(execcheckreturn("DROP FUNCTION IF EXISTS update_timer() CASCADE"));
	PQclear(execcheckreturn("CREATE FUNCTION update_timer() RETURNS trigger AS \'DECLARE "
			"lastrun TIMESTAMP(0) WITH TIME ZONE; runperiod INTERVAL SECOND(0); BEGIN "
			"lastrun = CURRENT_TIMESTAMP(0); "
			"runperiod = CAST(NEW.filter AS INTEGER) * INTERVAL \'\'0:0:1\'\'; "
			"IF (OLD.send_receive_seconds <> 2 OR NOT OLD.is_active) "
			"AND NEW.send_receive_seconds = 2 AND NEW.is_active THEN "
			"UPDATE rules SET (last_run, run_period, next_run) = (lastrun, "
			"runperiod, CAST(EXTRACT(EPOCH FROM lastrun + runperiod) AS BIGINT)) "
			"WHERE id = NEW.id AND username = NEW.username; "
			"ELSIF (NEW.send_receive_seconds <> 2 OR NOT NEW.is_active) "
			"AND OLD.send_receive_seconds = 2 AND OLD.is_active THEN "
			"DELETE FROM timers WHERE rule_id = NEW.id; "
			"ELSIF (OLD.filter <> NEW.filter) THEN UPDATE rules SET (run_period, next_run) "
			"= (runperiod, CAST(EXTRACT(EPOCH FROM last_run + runperiod) AS BIGINT)) "
			"WHERE id = NEW.id AND username = NEW.username; END IF; "
			"PERFORM refresh_next_timed_rule_time((SELECT MIN(next_run) FROM rules)); RETURN NULL; "
			"END;\' LANGUAGE PLPGSQL"));
	PQclear(execcheckreturn("CREATE TRIGGER update_timer AFTER UPDATE ON rules FOR ROW "
			"EXECUTE PROCEDURE update_timer()"));
	PQclear(execcheckreturn("SELECT refresh_next_timed_rule_time(("
			"SELECT MIN(next_run) FROM rules))"));
	PQclear(execcheckreturn("CALL config()"));
	PQclear(execcheckreturn("SET intervalstyle TO sql_standard"));
	main_loop();

	destroy_vars();
	PQfinish(conn);
}

template<typename type>
void instantiate_protocol_if_enabled() {
	PGresult *res = execcheckreturn("SELECT TRUE FROM proto_name WHERE name = \'"s
			+ get_typename(typeid(type)) + '\'');

	if (PQntuples(res) != 0) {
		protocols.push_back(new type);
	}
	PQclear(res);
}

const char *get_typename(const type_info &type) {
	const char *name = type.name();
#if (defined(__GLIBCXX__) || defined(__GLIBCPP__)) && !defined(__GABIXX_CXXABI_H_)
	int status;

	name = abi::__cxa_demangle(name, nullptr, nullptr, &status);
	THR(status != 0, system_exception("cannot demangle function"));
#endif
	return name;
}

void initialize_vars() {
	rlimit rl;
	mq_attr ma = { 0, 4, sizeof(raw_message *) };
	BYTE test[2] = { 1 };
	random_device rd;

	THR(getrlimit(RLIMIT_MSGQUEUE, &rl) < 0, system_exception("cannot getrlimit"));
	LOG_CPP("got RLIMIT_MSGQUEUE, rlim_cur = " << rl.rlim_cur << ", rlim_max = " << rl.rlim_max
			<< endl);
	if (rl.rlim_cur < PRLIMIT) {
		LOG_CPP("rlim_cur lower than " << PRLIMIT << ", raising to " << PRLIMIT << endl);
		rl.rlim_cur = PRLIMIT;
		if (rl.rlim_max < PRLIMIT) {
			LOG_CPP("rlim_max lower than " << PRLIMIT << ", raising to " << PRLIMIT << endl);
			rl.rlim_max = PRLIMIT;
		}
		THR(setrlimit(RLIMIT_MSGQUEUE, &rl) < 0, system_exception("cannot setrlimit"));
				//privileged operation!!!
		LOG_CPP("set RLIMIT_MSGQUEUE, rlim_cur = " << rl.rlim_cur << ", rlim_max = " << rl.rlim_max
				<< endl);
	}

	umask(0000);
	main_mq = mq_open("/main", O_RDWR | O_CREAT, 0777, &ma);
	THR(main_mq < 0, system_exception("cannot open main_mq"));
	ma.mq_msgsize = sizeof(ext_struct);
	ext_mq = mq_open("/ext", O_RDONLY | O_CREAT | O_NONBLOCK, 0777, &ma);
	THR(ext_mq < 0, system_exception("cannot open ext_mq"));
	ma.mq_msgsize = sizeof(send_inject_struct) - 1 + msg_MAX;
	send_inject_mq = mq_open("/send_inject", O_RDWR | O_CREAT | O_NONBLOCK, 0777, &ma);
	THR(send_inject_mq < 0, system_exception("cannot open send_inject_mq"));
	ma.mq_msgsize = sizeof(long);
	prlimit_pid_mq = mq_open("/prlimit_pid", O_RDONLY | O_CREAT | O_NONBLOCK, 0777, &ma);
	THR(prlimit_pid_mq < 0, system_exception("cannot open prlimit_pid_mq"));
	prlimit_ack_mq = mq_open("/prlimit_ack", O_WRONLY | O_CREAT, 0777, &ma);
	THR(prlimit_ack_mq < 0, system_exception("cannot open prlimit_ack_mq"));
	ma.mq_msgsize = sizeof(load_store_struct);
	load_store_mq = mq_open("/load_store", O_RDONLY | O_CREAT | O_NONBLOCK, 0777, &ma);
	THR(load_store_mq < 0, system_exception("cannot open load_store_mq"));
	ma.mq_msgsize = sizeof(load_ack_struct);
	load_ack_mq = mq_open("/load_ack", O_WRONLY | O_CREAT | O_NONBLOCK, 0777, &ma);
	THR(load_ack_mq < 0, system_exception("cannot open load_ack_mq"));
	ma.mq_msgsize = sizeof(config_struct);
	config_mq = mq_open("/config", O_RDONLY | O_CREAT | O_NONBLOCK, 0777, &ma);
	THR(config_mq < 0, system_exception("cannot open config_mq"));
	ma.mq_msgsize = sizeof(refresh_next_timed_rule_time_struct);
	refresh_next_timed_rule_time_mq = mq_open("/refresh_next_timed_rule_time",
			O_RDONLY | O_CREAT | O_NONBLOCK, 0777, &ma);
	THR(refresh_next_timed_rule_time_mq < 0,
			system_exception("cannot open refresh_next_timed_rule_time_mq"));
	ma.mq_msgsize = sizeof(update_permissions_struct);
	update_permissions_mq = mq_open("/update_permissions",
			O_RDONLY | O_CREAT | O_NONBLOCK, 0777, &ma);
	THR(update_permissions_mq < 0, system_exception("cannot open update_permissions_mq"));
	manually_execute_timed_rule_mq = mq_open("/manually_execute_timed_rule",
			O_RDWR | O_CREAT | O_NONBLOCK, 0777, &ma);
	THR(manually_execute_timed_rule_mq < 0,
			system_exception("cannot open manually_execute_timed_rule_mq"));

	cipherctx = EVP_CIPHER_CTX_new();
	mdctx = EVP_MD_CTX_new();
	ciphertype = EVP_aes_256_cbc();
	mdtype = EVP_sha256();
	blocksizetimes2minus1 = (EVP_CIPHER_block_size(ciphertype) << 1) - 1;
	ivlength = EVP_CIPHER_iv_length(ciphertype);

#ifdef SIGNAL
	signal_context_create(&global_context, nullptr);
	signal_protocol_store_context_create(&store_context, global_context);
	pthread_mutexattr_init(&global_mutexattr);
	pthread_mutexattr_settype(&global_mutexattr, PTHREAD_MUTEX_RECURSIVE);
	pthread_mutex_init(&global_mutex, &global_mutexattr);
	signal_context_set_locking_functions(global_context, test_lock, test_unlock);
	signal_crypto_provider provider = {
		test_random_generator, test_hmac_sha256_init, test_hmac_sha256_update,
		test_hmac_sha256_final, test_hmac_sha256_cleanup, test_sha512_digest_init,
		test_sha512_digest_update, test_sha512_digest_final, test_sha512_digest_cleanup,
		test_encrypt, test_decrypt, nullptr
	};
	signal_context_set_crypto_provider(context, &provider);
#endif

	if (*reinterpret_cast<BYTE2 *>(test) != 1) {
		little_endian = false;
	}
	beginning = my_now();
	dre.seed(rd());
	determine_local_addr();
	local_FROM = local_FROM + BYTE8_to_c17charp(local_addr) + ' ';
}

void destroy_vars() {
	for (auto a_r : addr_remote) {
		for (auto D_I_T : a_r.second->DST_ID_TWR) {
			delete D_I_T.second;
		}
		for (auto p_i_T : a_r.second->proto_iSRC_TWR) {
			delete p_i_T.second;
		}
		delete a_r.second;
	}
	for (auto t_u : table_user) {
		delete t_u.second;
	}
	for (auto u_c : username_configuration) {
		delete u_c.second;
	}
	for (protocol *p : protocols) {
		delete p;
	}

	THR(mq_unlink("/main") < 0, system_exception("cannot unlink main_mq"));
	THR(mq_unlink("/ext") < 0, system_exception("cannot unlink ext_mq"));
	THR(mq_unlink("/send_inject") < 0, system_exception("cannot unlink send_inject_mq"));
	THR(mq_unlink("/prlimit_pid") < 0, system_exception("cannot unlink prlimit_pid_mq"));
	THR(mq_unlink("/prlimit_ack") < 0, system_exception("cannot unlink prlimit_ack_mq"));
	THR(mq_unlink("/load_store") < 0, system_exception("cannot unlink load_store_mq"));
	THR(mq_unlink("/load_ack") < 0, system_exception("cannot unlink load_ack_mq"));
	THR(mq_unlink("/config") < 0, system_exception("cannot unlink config_mq"));
	THR(mq_unlink("/refresh_next_timed_rule_time") < 0,
			system_exception("cannot unlink refresh_next_timed_rule_time_mq"));
	THR(mq_unlink("/update_permissions") < 0,
			system_exception("cannot unlink update_permissions_mq"));
	THR(mq_unlink("/manually_execute_timed_rule") < 0,
			system_exception("cannot unlink manually_execute_timed_rule_mq"));

	EVP_CIPHER_CTX_free(cipherctx);
	EVP_MD_CTX_free(mdctx);

#ifdef SIGNAL
	pthread_mutexattr_destroy(&global_mutexattr);
	pthread_mutex_destroy(&global_mutex);
	signal_protocol_store_context_destroy(store_context);
	signal_context_destroy(global_context);
#endif
}

void sig_ign(int sig) {
	LOG_CPP("handling signal " << strsignal(sig) << " for thread " << this_thread::get_id()
			<< endl);
}

BYTE8 EUI64_to_EUI48(BYTE8 EUI64) noexcept {
	return (EUI64 >> 16 & 0x0000FFFF'FFFFFFFF) | (EUI64 & 0x00000000'0000FFFF);
}

void populate_local_proto_iaddr() {
	int sock = socket(AF_INET, SOCK_STREAM, 0), i = MAX_DEVICE_INDEX;
	ifreq ifr;
	BYTE8 addr;
	hci_dev_info hdi;
	int dd;
	bdaddr_t bdaddr_any = { { 0 } };

	while (find_next_lower_device(sock, ifr, i) >= 0) {
		if (ioctl(sock, SIOCGIFADDR, &ifr) < 0) {
			THR(errno != EADDRNOTAVAIL, system_exception("cannot SIOCGIFADDR ioctl"));
		} else if (ifr.ifr_addr.sa_family == AF_INET) {
			addr = ia_to_BYTE8(reinterpret_cast<sockaddr_in *>(&ifr.ifr_addr)->sin_addr);
			local_proto_iaddr.insert(make_pair(find_protocol_by_name("tcp"), addr));
			LOG_CPP("added imm_addr " << BYTE8_to_c17charp(addr) << " for TCP" << endl);
			local_proto_iaddr.insert(make_pair(find_protocol_by_name("udp"), addr));
			LOG_CPP("added imm_addr " << BYTE8_to_c17charp(addr) << " for UDP" << endl);
		} else {
			THR(ioctl(sock, SIOCGIFHWADDR, &ifr) < 0,
					system_exception("cannot SIOCGIFADDR ioctl"));//cannot cause EADDRNOTAVAIL
			if (ifr.ifr_hwaddr.sa_family == ARPHRD_IEEE802154) {
				memcpy_endian(&addr,
						reinterpret_cast<sockaddr_ieee802154 *>(&ifr.ifr_hwaddr)->addr.hwaddr, 8);
				local_proto_iaddr.insert(make_pair(find_protocol_by_name("154"), addr));
				LOG_CPP("added imm_addr " << BYTE8_to_c17charp(addr) << " for 154" << endl);
			}
		}
	}
	close(sock);
	sock = socket(AF_BLUETOOTH, SOCK_RAW, BTPROTO_HCI);
	hdi.dev_id = 0;
	THR(ioctl(sock, HCIGETDEVINFO, &hdi) < 0, system_exception("cannot HCIGETDEVINFO ioctl"));
	if (hci_test_bit(HCI_RAW, &hdi.flags) && !bacmp(&hdi.bdaddr, &bdaddr_any)) {
		dd = hci_open_dev(0);
		hci_read_bd_addr(dd, reinterpret_cast<bdaddr_t *>(&addr), 8);
		hci_close_dev(dd);
		local_proto_iaddr.insert(make_pair(find_protocol_by_name("ble"), addr));
		LOG_CPP("added imm_addr " << BYTE8_to_c17charp(addr) << " for BLE" << endl);
	}
	close(sock);
}

void determine_local_addr() {
	int sock = socket(AF_UNIX, SOCK_SEQPACKET, 0), i = MAX_DEVICE_INDEX;
	ifreq ifr;

	while (find_next_lower_device(sock, ifr, i) >= 0) {
		THR(ioctl(sock, SIOCGIFHWADDR, &ifr) < 0,
				system_exception("cannot SIOCGIFHWADDR ioctl"));//cannot cause EADDRNOTAVAIL
		if (ifr.ifr_hwaddr.sa_family == ARPHRD_IEEE80211) {
			if (little_endian) {
				memcpy_reverse(&local_addr, ifr.ifr_hwaddr.sa_data, 6);
			} else {
				memcpy(reinterpret_cast<BYTE *>(&local_addr) + 2, ifr.ifr_hwaddr.sa_data, 6);
			}
			local_addr = EUI48_to_EUI64(local_addr);
			LOG_CPP("determined local_SRC to " << BYTE8_to_c17charp(local_addr) << endl);
			break;
		}
	}
	close(sock);
}

const char *BYTE8_to_c17charp(BYTE8 address) {
	int i = 16;
	static char ret[17] = { '\0' };

	while (--i >= 0) {//does not depend on endianness
		ret[15 - i] = hex_to_text[(address >> (i << 2)) & 0x00000000'0000000F];
	}
	THR(ret[16] != '\0', system_exception("error in BYTE8_to_c17charp"));
	return ret;
}

PGresult *execcheckreturn(string query) {
	ExecStatusType est;
	PQprintOpt opt = { 0 };
	PGresult *res;

	LOG_CPP("executing \"" << query << ";\"" << endl);
	res = PQexec(conn, (query + ';').c_str());
	est = PQresultStatus(res);
	if (est != PGRES_COMMAND_OK && est != PGRES_TUPLES_OK) {
		LOG_CPP(PQresultErrorMessage(res));
		PQclear(res);
		throw database_exception("error in command");
	}
	if (est != PGRES_COMMAND_OK) {
		opt.header = 1;
		opt.align = 1;
		opt.fieldSep = const_cast<char *>("|");
		LOG_CPP("i got" << endl);
		LOG_CPP(endl);
		LOG_C(PQprint, res, &opt);
		LOG_CPP("as answer" << endl);
	} else {
		LOG_CPP("i got COMMAND_OK as answer" << endl);
	}
	return res;
}

#if __PIC__ == 1

//this function is executed in another process!!!
extern "C" Datum manually_execute_timed_rule(PG_FUNCTION_ARGS) {
	text *username_sql = PG_GETARG_TEXT_PP(0);
	int username_len = VARSIZE_ANY_EXHDR(username_sql);
	int id = PG_GETARG_INT32(1);
	manually_execute_timed_rule_struct metrs = { "", id };
	mqd_t manually_execute_timed_rule_mq = mq_open("/manually_execute_timed_rule", O_WRONLY);

	THR(manually_execute_timed_rule_mq < 0,
			system_exception("cannot open manually_execute_timed_rule_mq"));
	memcpy(metrs.username, VARDATA_ANY(username_sql), username_len < 256 ? username_len : 256);
	THR(mq_send(manually_execute_timed_rule_mq, reinterpret_cast<char *>(&metrs),
			sizeof(manually_execute_timed_rule_struct), 0) < 0,
			system_exception("cannot send to manually_execute_timed_rule_mq"));
	PG_RETURN_VOID();
}

//this function is executed in another process!!!
extern "C" Datum ext(PG_FUNCTION_ARGS) {
	text *addr_id_sql = PG_GETARG_TEXT_PP(0);
	int addr_id_len = VARSIZE_ANY_EXHDR(addr_id_sql);
	ext_struct es = { { '\0' } };
	mqd_t ext_mq = mq_open("/ext", O_WRONLY);

	THR(ext_mq < 0, system_exception("cannot open ext_mq"));
	memcpy(es.addr_id, VARDATA_ANY(addr_id_sql), addr_id_len < 27 ? addr_id_len : 27);
	THR(mq_send(ext_mq, reinterpret_cast<char *>(&es), sizeof(ext_struct), 0) < 0,
			system_exception("cannot send to ext_mq"));
	THR(mq_close(ext_mq) < 0, system_exception("cannot close ext_mq"));
	PG_RETURN_VOID();
}

//this function is executed in another process!!!
extern "C" Datum send_inject(PG_FUNCTION_ARGS) {
	bool send = PG_GETARG_BOOL(0), CCF = PG_GETARG_BOOL(4), ACF = PG_GETARG_BOOL(5),
			broadcast = PG_GETARG_BOOL(6), override_implicit_rules = PG_GETARG_BOOL(7);
	bytea *message = PG_GETARG_BYTEA_PP(1), *imm_addr = PG_GETARG_BYTEA_PP(3);
	text *proto_id_sql = PG_GETARG_TEXT_PP(2);
	int message_length = VARSIZE_ANY_EXHDR(message), proto_id_len = VARSIZE_ANY_EXHDR(proto_id_sql),
			fd = open("/tmp/flock_cpp", O_WRONLY | O_CREAT);
	send_inject_struct *sis = new(message_length) send_inject_struct(message_length);
	mqd_t prlimit_pid_mq = mq_open("/prlimit_pid", O_WRONLY), prlimit_ack_mq
			= mq_open("/prlimit_ack", O_RDWR), send_inject_mq = mq_open("/send_inject", O_WRONLY);
	long pid = getpid(), temp = pid;

	THR(prlimit_pid_mq < 0, system_exception("cannot open prlimit_pid_mq"));
	THR(prlimit_ack_mq < 0, system_exception("cannot open prlimit_ack_mq"));
	THR(send_inject_mq < 0, system_exception("cannot open send_inject_mq"));
	THR(fd < 0, system_exception("cannot open fd"));
	memset(sis->proto_id, 0, 13);
	memcpy(sis->proto_id, VARDATA_ANY(proto_id_sql), proto_id_len < 12 ? proto_id_len : 12);
	memcpy(sis->imm_addr, VARDATA_ANY(imm_addr), 8);
	sis->CCF = CCF;
	sis->ACF = ACF;
	sis->send = send;
	sis->broadcast = broadcast;
	sis->override_implicit_rules = override_implicit_rules;
	sis->message_length = message_length;
	memcpy(sis->message_content, VARDATA_ANY(message), message_length);
	THR(mq_send(prlimit_pid_mq, reinterpret_cast<char *>(&pid), sizeof(pid), 0) < 0,
			system_exception("cannot send to prlimit_pid_mq"));
	THR(mq_close(prlimit_pid_mq) < 0, system_exception("cannot close prlimit_pid_mq"));
	do {
		THR(mq_receive(prlimit_ack_mq, reinterpret_cast<char *>(&pid), sizeof(pid), 0) < 0,
				system_exception("cannot receive from prlimit_ack_mq"));
		if (pid == temp) {
			break;
		}
		THR(mq_send(prlimit_ack_mq, reinterpret_cast<char *>(&pid), sizeof(pid), 0) < 0,
				system_exception("cannot send to prlimit_ack_mq"));
	} while (true);
	THR(mq_close(prlimit_ack_mq) < 0, system_exception("cannot close prlimit_ack_mq"));
	THR(flock(fd, LOCK_EX) != 0, system_exception("cannot lock fd"));
	THR(mq_send(send_inject_mq, reinterpret_cast<char *>(&message_length), sizeof(message_length),
			0) < 0, system_exception("cannot send to send_inject_mq"));
	THR(mq_send(send_inject_mq, reinterpret_cast<char *>(sis), sizeof(send_inject_struct) - 1
			+ message_length, 0) < 0, system_exception("cannot send to send_inject_mq"));
	THR(close(fd) < 0, system_exception("cannot close fd"));
	THR(mq_close(send_inject_mq) < 0, system_exception("cannot close send_inject_mq"));
	delete sis;
	PG_RETURN_VOID();
}

#endif

void ext2(const ext_struct &es) {
	const_cast<ext_struct &>(es).addr_id[16] = '\0';
	PQclear(formatsendreturn(execcheckreturn("TABLE table_"s + es.addr_id),
			c17charp_to_BYTE8(es.addr_id)));
}

int find_next_lower_bluetooth(int sock, hci_dev_info &hdi, int &i) {
	while (--i >= 0) {
		hdi.dev_id = i;
		if (ioctl(sock, HCIGETDEVINFO, &hdi) < 0) {
			THR(errno != ENODEV, system_exception("cannot HCIGETDEVINFO ioctl"));
			continue;
		}
		LOG_CPP("found bluetooth " << hdi.name << " at index " << i << endl);
		return i;
	}
	return -1;
}

protocol *find_protocol_by_id(const char *id) {
	for (protocol *p : protocols) {
		if (p->get_my_id() == id) {
			return p;
		}
	}
	throw system_exception("protocol not found");
}

protocol *find_protocol_by_name(const char *name) {
	for (protocol *p : protocols) {
		if (strcmp(get_typename(typeid(*p)), name) == 0) {
			return p;
		}
	}
	return nullptr;
}

int find_next_lower_device(int sock, ifreq &ifr, int &i) {
	while (--i >= 0) {
		ifr.ifr_ifindex = i;
		if (ioctl(sock, SIOCGIFNAME, &ifr) < 0) {
			THR(errno != ENODEV, system_exception("cannot SIOCGIFNAME ioctl"));
			continue;
		}
		LOG_CPP("found device " << ifr.ifr_name << " at index " << i << endl);
		return i;
	}
	return -1;
}

void send_inject2(const send_inject_struct &sis) {
	raw_message *rmsg = new raw_message(new BYTE[sis.message_length]);
	unique_ptr<raw_message> dummy(rmsg);
	unique_ptr<formatted_message> fmsg(new (sis.message_length)
			formatted_message(sis.message_length));

	memcpy_endian(&rmsg->imm_addr, sis.imm_addr, 8);
	LOG_CPP("received for " << (sis.send ? "sending" : "injecting") << ": \"");
	LOG_CP(print_message_c, sis.message_content, sis.message_length);
	LOG_CPP("\" with protocol id \"" << sis.proto_id << "\" and imm_" << (sis.send ? "DST" : "SRC")
			<< ' ' << BYTE8_to_c17charp(rmsg->imm_addr) << " with " << (sis.CCF ? "CCF" : "!CCF")
			<< " and " << (sis.ACF ? "ACF" : "!ACF") << " and broadcast = "
			<< BOOLALPHA_UPPERCASE(sis.broadcast) << " and override_implicit_rules = "
			<< BOOLALPHA_UPPERCASE(sis.override_implicit_rules) << endl);
	rmsg->TML = sis.message_length;
	rmsg->proto = find_protocol_by_id(sis.proto_id);
	memcpy(rmsg->msg, sis.message_content, rmsg->TML);
	rmsg->TWR = my_now();
	rmsg->broadcast = sis.broadcast;
	rmsg->override_implicit_rules = sis.override_implicit_rules;
	rmsg->CCF = sis.CCF;
	rmsg->ACF = sis.ACF;
	if (sis.send) {
		decode_message(*rmsg, *fmsg);
		security_check_for_sending(*fmsg, *rmsg);
	}
	THR(mq_send(sis.send ? rmsg->proto->get_my_mq() : main_mq, reinterpret_cast<char *>(&rmsg),
			sizeof(rmsg), 0) < 0, system_exception("cannot send to queue"));
	dummy.release();
}

//this function is executed in another process!!!
extern "C" Datum load_store(PG_FUNCTION_ARGS) {
	load_store_struct lss = { PG_GETARG_BOOL(0) };
	load_ack_struct las;
	mqd_t load_store_mq = mq_open("/load_store", O_WRONLY), load_ack_mq;

	THR(load_store_mq < 0, system_exception("cannot open load_store_mq"));
	THR(mq_send(load_store_mq, reinterpret_cast<char *>(&lss), sizeof(load_store_struct), 0) < 0,
			system_exception("cannot send to load_store_mq"));
	THR(mq_close(load_store_mq) < 0, system_exception("cannot close load_store_mq"));
	if (lss.load) {
		load_ack_mq = mq_open("/load_ack", O_RDONLY);
		THR(load_ack_mq < 0, system_exception("cannot open load_ack_mq"));
		THR(mq_receive(load_ack_mq, reinterpret_cast<char *>(&las), sizeof(load_ack_struct),
				nullptr) < 0, system_exception("cannot receive from load_ack_mq"));
		THR(mq_close(load_ack_mq) < 0, system_exception("cannot close load_ack_mq"));
	}
	PG_RETURN_VOID();
}

//this function is executed in another process!!!
extern "C" Datum config(PG_FUNCTION_ARGS) {
	config_struct cs;
	mqd_t config_mq = mq_open("/config", O_WRONLY);

	THR(config_mq < 0, system_exception("cannot open config_mq"));
	THR(mq_send(config_mq, reinterpret_cast<char *>(&cs), sizeof(config_struct), 0) < 0,
			system_exception("cannot send to config_mq"));
	THR(mq_close(config_mq) < 0, system_exception("cannot close config_mq"));
	PG_RETURN_VOID();
}

//this function is executed in another process!!!
extern "C" Datum refresh_next_timed_rule_time(PG_FUNCTION_ARGS) {
	refresh_next_timed_rule_time_struct rntrts = { PG_GETARG_INT64(0) };
	mqd_t refresh_next_timed_rule_time_mq = mq_open("/refresh_next_timed_rule_time", O_WRONLY);

	THR(refresh_next_timed_rule_time_mq < 0,
			system_exception("cannot open refresh_next_timed_rule_time_mq"));
	THR(mq_send(refresh_next_timed_rule_time_mq, reinterpret_cast<char *>(&rntrts),
			sizeof(refresh_next_timed_rule_time_struct), 0) < 0,
			system_exception("cannot send to refresh_next_timed_rule_time_mq"));
	THR(mq_close(refresh_next_timed_rule_time_mq) < 0,
			system_exception("cannot close refresh_next_timed_rule_time_mq"));
	PG_RETURN_VOID();
}

//this function is executed in another process!!!
extern "C" Datum update_permissions(PG_FUNCTION_ARGS) {
	struct update_permissions_struct ups;
	mqd_t update_permissions_mq = mq_open("/update_permissions", O_WRONLY);

	THR(update_permissions_mq < 0, system_exception("cannot open update_permissions_mq"));
	THR(mq_send(update_permissions_mq, reinterpret_cast<char *>(&ups),
			sizeof(update_permissions_struct), 0) < 0,
			system_exception("cannot send to update_permissions_mq"));
	THR(mq_close(update_permissions_mq) < 0,
			system_exception("cannot close update_permissions_mq"));
	PG_RETURN_VOID();
}

void load_store2_load() {
	ostringstream oss(oss.out | oss.ate);
	string addr;

	PQclear(execcheckreturn("TRUNCATE TABLE addr_oID CASCADE"));
	for (auto a_r : addr_remote) {
		oss.str("INSERT INTO addr_oID(addr, out_ID) VALUES(E\'\\\\x");
		addr = BYTE8_to_c17charp(a_r.first);
		oss << addr << "\', " << static_cast<int>(a_r.second->out_ID) << ')';
		PQclear(execcheckreturn(oss.str()));
		for (auto D_I_T : a_r.second->DST_ID_TWR) {
			oss.str("INSERT INTO SRC_DST(SRC, DST) VALUES(E\'\\\\x");
			oss << addr << "\', E\'\\\\x" << BYTE8_to_c17charp(D_I_T.first) << "\')";
			PQclear(execcheckreturn(oss.str()));
			if (!D_I_T.second->empty()) {
				oss.str("INSERT INTO ID_TWR(SRC, DST, ID, TWR) VALUES(E\'\\\\x");
				for (auto I_T : *D_I_T.second) {
					oss << addr << "\', " << static_cast<int>(I_T.first) << ", TIMESTAMP \'"
							<< I_T.second << "\'), (E\'\\\\x";
				}
				oss.seekp(-8, oss.end) << '\0';
				PQclear(execcheckreturn(oss.str()));
			}
		}
		for (auto p_i_T : a_r.second->proto_iSRC_TWR) {
			oss.str("INSERT INTO SRC_proto(SRC, proto) VALUES(E\'\\\\x");
			oss << addr << "\', \'" << p_i_T.first->get_my_id() << "\')";
			PQclear(execcheckreturn(oss.str()));
			if (!p_i_T.second->empty()) {
				oss.str("INSERT INTO iSRC_TWR(SRC, proto, imm_SRC, TWR) VALUES(E\'\\\\x");
				for (auto i_T : *p_i_T.second) {
					oss << addr << "\', \'" << p_i_T.first->get_my_id() << "\', E\'\\\\x"
							<< BYTE8_to_c17charp(i_T.first) << "\', TIMESTAMP \'" << i_T.second
							<< "\'), (E\'\\\\x";
				}
				oss.seekp(-8, oss.end) << '\0';
				PQclear(execcheckreturn(oss.str()));
			}
		}
	}
}

void load_store2_store() {
	istringstream iss;
	const char *temp, *addr;
	PGresult *res, *res2, *res3;
	BYTE ID;
	map<BYTE8, remote *>::iterator iter_a_r;
	map<BYTE8, map<BYTE, my_time_point> *>::iterator iter_D_I_T;
	map<protocol *, map<BYTE8, my_time_point> *>::iterator iter_p_i_T;
	my_time_point TWR;
	int i = 0, j, k, l, m, n, id;

	for (auto a_r : addr_remote) {
		for (auto D_I_T : a_r.second->DST_ID_TWR) {
			delete D_I_T.second;
		}
		for (auto p_i_T : a_r.second->proto_iSRC_TWR) {
			delete p_i_T.second;
		}
		delete a_r.second;
	}
	addr_remote.clear();
	res = execcheckreturn("TABLE addr_oID ORDER BY addr ASC");
	for (j = PQntuples(res); i < j; i++) {
		addr = PQgetvalue(res, i, 0) + 2;
		iter_a_r = addr_remote.insert(make_pair(c17charp_to_BYTE8(addr), new remote)).first;
		res2 = execcheckreturn("SELECT DST FROM SRC_DST WHERE SRC = E\'\\\\x"s + addr
				+ "\' ORDER BY DST ASC");
		for (k = 0, l = PQntuples(res2); k < l; k++) {
			temp = PQgetvalue(res2, k, 0) + 2;
			iter_D_I_T = iter_a_r->second->DST_ID_TWR.insert(make_pair(c17charp_to_BYTE8(
					temp), new map<BYTE, my_time_point>)).first;
			res3 = execcheckreturn("SELECT ID, TWR FROM ID_TWR WHERE SRC = E\'\\\\x"s + addr
					+ "\' AND DST = E\'\\\\x" + temp + "\' ORDER BY ID ASC");
			for (m = 0, n = PQntuples(res3); m < n; m++) {
				iss.str(PQgetvalue(res3, m, 0));
				iss >> ID;
				iss.clear();
				iss.str(PQgetvalue(res3, m, 1));
				iss >> TWR;
				iss.clear();
				iter_D_I_T->second->insert(make_pair(ID, TWR));
			}
			PQclear(res3);
		}
		PQclear(res2);
		iss.str(PQgetvalue(res, i, 1));
		iss >> id;
		iter_a_r->second->out_ID = id;
		iss.clear();
		res2 = execcheckreturn("SELECT proto FROM SRC_proto WHERE SRC = E\'\\\\x"s + addr
				+ "\' ORDER BY proto ASC");
		for (k = 0, l = PQntuples(res2); k < l; k++) {
			temp = PQgetvalue(res2, k, 0);
			iter_p_i_T = iter_a_r->second->proto_iSRC_TWR.insert(make_pair(find_protocol_by_id(
					temp), new map<BYTE8, my_time_point>)).first;
			res3 = execcheckreturn("SELECT imm_SRC, TWR FROM iSRC_TWR WHERE SRC = E\'\\\\x"s
					+ addr + "\' AND proto = \'" + temp + "\' ORDER BY imm_SRC ASC");
			for (m = 0, n = PQntuples(res3); m < n; m++) {
				iss.str(PQgetvalue(res3, m, 1));
				iss >> TWR;
				iss.clear();
				iter_p_i_T->second->insert(make_pair(c17charp_to_BYTE8(PQgetvalue(res3, m, 0) + 2),
						TWR));
			}
			PQclear(res3);
		}
		PQclear(res2);
	}
	PQclear(res);
	PQclear(execcheckreturn("TRUNCATE TABLE addr_oID CASCADE"));
}

void config2() {
	PGresult *res = execcheckreturn("TABLE configuration ORDER BY username DESC");
	istringstream iss;
	configuration *c;
	int i = PQntuples(res);

	username_configuration.clear();
	while (--i >= 0) {
		c = new configuration;
		c->forward_messages = *PQgetvalue(res, i, 1) == 't';
		c->use_internet_switch_algorithm = *PQgetvalue(res, i, 2) == 't';
		iss.str(PQgetvalue(res, i, 3));
		iss >> c->nsecs_id;
		iss.clear();
		iss.str(PQgetvalue(res, i, 4));
		iss >> c->nsecs_src;
		iss.clear();
		c->trust_everyone = *PQgetvalue(res, i, 5) == 't';
		c->default_gateway = c17charp_to_BYTE8(PQgetvalue(res, i, 6) + 2);
		username_configuration.insert(make_pair(PQgetvalue(res, i, 0), c));
	}
	PQclear(res);
}

void encode_message(formatted_message &fmsg, raw_message &rmsg) {
	int i;

	*rmsg.msg = fmsg.HD.get_as_byte();
	i = 1;
	if (fmsg.HD.I) {
		rmsg.msg[1] = fmsg.ID;
		i = 2;
	}
	if (fmsg.HD.L) {
		memcpy_endian(rmsg.msg + i, &fmsg.LEN, 2);
		i += 2;
	}
	if (fmsg.HD.D) {
		memcpy_endian(rmsg.msg + i, &fmsg.DST, 8);
		i += 8;
	}
	if (fmsg.HD.S) {
		memcpy_endian(rmsg.msg + i, &fmsg.SRC, 8);
		i += 8;
	}
	memcpy(rmsg.msg + i, fmsg.PL, fmsg.LEN);
	rmsg.TML = i + fmsg.LEN;
	if (fmsg.HD.R) {
		memcpy_endian(rmsg.msg + rmsg.TML, &fmsg.CRC, 4);
		rmsg.TML += 4;
	}
}

void refresh_next_timed_rule_time2(const refresh_next_timed_rule_time_struct &rntrts) {
	next_timed_rule = rntrts.next_timed_rule;
	LOG_CPP("next timed rule " << next_timed_rule - time(nullptr) << " seconds from now" << endl);
}

void update_permissions2() {
	PGresult *res = execcheckreturn("TABLE table_user ORDER BY tablename DESC");
	int i = PQntuples(res);

	table_user.clear();
	while (--i >= 0) {
		table_user.insert(make_pair(PQgetvalue(res, i, 0), PQgetisnull(res, i, 1)
				? nullptr : new string(PQgetvalue(res, i, 1))));
	}
	PQclear(res);
}

void decode_message(raw_message &rmsg, formatted_message &fmsg) {
	int i;
	BYTE4 CRC;
	if (rmsg.TML > 1) {
		fmsg.HD.put_as_byte(*rmsg.msg);
		i = 1;
	} else {
		fmsg.HD.put_as_byte(0b00000000);
		i = 0;
	}
	if (fmsg.HD.I) {
		THR(1 >= rmsg.TML, message_exception("ID absent"));
		fmsg.ID = rmsg.msg[1];
		i = 2;
	}
	if (fmsg.HD.L) {
		THR(i + 1 >= rmsg.TML, message_exception("LEN absent"));
		memcpy_endian(&fmsg.LEN, rmsg.msg + i, 2);
		i += 2;
	}
	if (fmsg.HD.D) {
		THR(i + 7 >= rmsg.TML, message_exception("DST absent"));
		memcpy_endian(&fmsg.DST, rmsg.msg + i, 8);
		i += 8;
	} else {
		fmsg.DST = local_addr;
	}
	if (fmsg.HD.S) {
		THR(i + 7 >= rmsg.TML, message_exception("SRC absent"));
		memcpy_endian(&fmsg.SRC, rmsg.msg + i, 8);
		i += 8;
	} else {
		fmsg.SRC = rmsg.imm_addr;
	}
	memcpy(fmsg.PL, rmsg.msg + i, rmsg.TML - i);
	if (fmsg.HD.R) {
		THR(i + 3 >= rmsg.TML, message_exception("CRC absent"));
		memcpy_endian(&fmsg.CRC, rmsg.msg + rmsg.TML - 4, 4);
		CRC = givecrc32c(rmsg.msg, rmsg.TML - 4);
		if (fmsg.CRC != CRC) {
			LOG_CPP("received CRC " << HEX(fmsg.CRC, 8)
					<< " != calculated CRC " << HEX(CRC, 8) << endl);
			throw message_exception("wrong CRC");
		}
		i += 4;
	} else {
		fmsg.CRC = givecrc32c(rmsg.msg, rmsg.TML);
	}
	if (!fmsg.HD.I) {
		fmsg.ID = fmsg.CRC;
	}
	if (!fmsg.HD.L) {
		fmsg.LEN = rmsg.TML - i;
	} else if (fmsg.LEN != rmsg.TML - i) {
		LOG_CPP("received LEN " << fmsg.LEN
				<< " != calculated LEN " << rmsg.TML - i << endl);
		throw message_exception("wrong LEN");
	}
}

void main_loop() {
	int i, id;
	unique_ptr<formatted_message> fmsg;
	string query, remote_FROM_prefix(" t");
	istringstream iss;
	ostringstream oss(oss.out | oss.ate);
	PGresult *res;

	do {
		try {
			fmsg.reset(receive_formatted_message());
			if (fmsg != nullptr) {
				query.assign(reinterpret_cast<char *>(fmsg->PL), fmsg->LEN);
				switch (query.front()) {
				case 'T'://TABLE
					if (query.length() > 1 && query[1] == 'I') {
						LOG_CPP("TIME(STAMP) not TABLE" << endl);
						ins(query, fmsg->SRC);
					}
					//no break
				case 'W'://WITH
				case 'S'://SELECT
				case '('://nested queries
				case '\xEA'://TABLE
				case '\xFD'://WITH
				case '\xBE'://SELECT
					/* SELECT(_SUBSCRIBE) */
					convert_select(query, remote_FROM_prefix + BYTE8_to_c17charp(fmsg->SRC) + ' ');
					format_select(query);
					i = query.rfind(" SUBSCRIBE ");
					if (i != static_cast<int>(string::npos)) {
						iss.str(query.substr(i + 11));//strlen(" SUBSCRIBE ")=11
						if (!(iss >> id).fail()) {
							oss.str("_");
							oss << id;//preventing SQL injection
							LOG_CPP("calling subscribe" << endl);
							sub(query.substr(0, i), oss.str(), fmsg->SRC);
						}
						iss.clear();
					} else {
						LOG_CPP("calling select" << endl);
						sel(query, fmsg->SRC);
					}
					break;
				case 'U'://UNSUBSCRIBE
					if (query.length() > 1 && query[1] == '&') {
						LOG_CPP("U&(UESCAPE) not UNSUBSCRIBE" << endl);
						ins(query, fmsg->SRC);
					}
					//no break
				case '\xF6'://UNSUBSCRIBE=0xF6//ALL=0x85
					/* UNSUBSCRIBE(_ALL) */
					query = query.substr(query.front() == '\xF6' ? 1 : 11);
					if (query == "\x85" || query == " ALL;") {
						res = execcheckreturn("SELECT SUBSTRING(relname FROM 22) FROM pg_class "
								"WHERE relname LIKE \'table\\_"s + BYTE8_to_c17charp(fmsg->SRC)
								+ "\\_%\'");
						for (i = PQntuples(res) - 1; i >= 0; i--) {
							unsub(PQgetvalue(res, i, 0), fmsg->SRC);
						}
						PQclear(res);
					} else if (!(iss >> id).fail()) {
						oss.str("_");
						oss << id;//preventing SQL injection
						LOG_CPP("calling unsubscribe" << endl);
						unsub(oss.str(), fmsg->SRC);
					}
					iss.clear();
					break;
				case 'K':
					LOG_CPP("received ACKNOWLEDGMENT for message " << HEX(fmsg->ID, 2) << endl);
					break;
				case 'P':
					LOG_CPP("received PAYLOAD_ERROR for message " << HEX(fmsg->ID, 2) << endl);
					break;
				case 'O':
					LOG_CPP("received OPERATION_UNSUPPORTED for message " << HEX(fmsg->ID, 2)
							<< endl);
					break;
				default://data
					LOG_CPP("calling insert" << endl);
					ins(query, fmsg->SRC);
				}
				if (fmsg->HD.K) {
					LOG_CPP("sending ACKNOWLEDGMENT for message " << HEX(fmsg->ID, 2) << endl);
					send_control("K"s + *reinterpret_cast<char *>(fmsg->ID), fmsg->SRC, fmsg->DST);
				}
				continue;
			}
			break;
		} catch (error_exception &e) {
			LOG_CPP("sending PAYLOAD_ERROR for message " << HEX(fmsg->ID, 2) << endl);
			send_control("P"s + *reinterpret_cast<char *>(&fmsg->ID) + e.what(), fmsg->SRC,
					fmsg->DST);
		} catch (unsupported_exception &e) {
			LOG_CPP("sending OPERATION_UNSUPPORTED for message " << HEX(fmsg->ID, 2) << endl);
			send_control("O"s + *reinterpret_cast<char *>(&fmsg->ID) + e.what(), fmsg->SRC,
					fmsg->DST);
		} catch (message_exception &e) {
			//don't-care
		} catch (network_exception &e) {
			LOG_CPP(e.what() << endl);
			return;
		} catch (database_exception &e) {
			LOG_CPP(e.what() << endl);
			return;
		}
	} while (true);
}

ostream &operator<<(ostream &os, const my_time_point &point) noexcept {
	time_t t = chrono::system_clock::to_time_t(point);

	return os << put_time(localtime(&t), "%Y-%m-%d %H:%M:%S");
}

void security_check_for_sending(formatted_message &fmsg, raw_message &rmsg) {
	auto t_u = table_user.find("t"s + BYTE8_to_c17charp(fmsg.SRC));

	if (username_configuration[t_u != table_user.cend() && t_u->second != nullptr
			? *t_u->second : "root"]->trust_everyone) {
		LOG_CPP("trusting everyone for sending" << endl);
	} else if (rmsg.override_implicit_rules) {
		LOG_CPP("overriding rules for sending" << endl);
	} else {
		THR(fmsg.HD.C && !rmsg.CCF && !fmsg.is_encrypted(),
				message_exception("security for C and snd breached"));
		THR(fmsg.HD.A && !rmsg.ACF && !fmsg.is_signed(),
				message_exception("security for A and snd breached"));
	}
}

istream &operator>>(istream &is, my_time_point &point) noexcept {
	tm t;

	is >> get_time(&t, "%Y - %m - %d %H : %M : %S");
	point = chrono::system_clock::from_time_t(mktime(&t));
	return is;
}

void send_control(string payload, BYTE8 DST, BYTE8 SRC) {
	BYTE2 LEN = payload.length();
	unique_ptr<formatted_message> fmsg(new(LEN) formatted_message(LEN));
	map<BYTE8, remote *>::const_iterator iter = addr_remote.find(DST);

	fmsg->HD.put_as_byte(0b11111011);//ID,LEN,DST,SRC,CRC,CONF,AUTH
	THR(iter == addr_remote.cend(), message_exception("DST does not exist"));
	fmsg->ID = iter->second->out_ID++;
	memcpy_endian(&fmsg->LEN, &LEN, 2);
	memcpy_endian(&fmsg->DST, &DST, 8);
	memcpy_endian(&fmsg->SRC, &SRC, 8);
	payload.copy(reinterpret_cast<char *>(fmsg->PL), LEN);
	fmsg->HD.put_as_byte(header::reverse_byte(fmsg->HD.get_as_byte()));
	fmsg->CRC = givecrc32c(reinterpret_cast<BYTE *>(fmsg.get()) + 4, LEN + fields_MAX);
			//HD,ID,LEN,DST,SRC
	fmsg->HD.put_as_byte(header::reverse_byte(fmsg->HD.get_as_byte()));
	if (little_endian) {
		fmsg->LEN = LEN;
		fmsg->DST = DST;
		fmsg->SRC = SRC;
	}
	LOG_CPP("sending " << *fmsg << endl);
	send_formatted_message(fmsg.release());
}

void security_check_for_receiving(raw_message &rmsg, formatted_message &fmsg) {
	auto t_u = table_user.find("t"s + BYTE8_to_c17charp(fmsg.DST));

	if (username_configuration[t_u != table_user.cend() && t_u->second != nullptr
			? *t_u->second : "root"]->trust_everyone) {
		LOG_CPP("trusting everyone for receiving" << endl);
	} else if (rmsg.override_implicit_rules) {
		LOG_CPP("overriding checks for receiving" << endl);
	} else {
		THR(fmsg.HD.C && !rmsg.CCF && !fmsg.is_encrypted(),
				message_exception("security for C and rcv breached"));
		THR(fmsg.HD.A && !rmsg.ACF && !fmsg.is_signed(),
				message_exception("security for A and rcv breached"));
	}
}

formatted_message *receive_formatted_message() {
	ostringstream oss(oss.out | oss.ate);
	unique_ptr<raw_message> rmsg;
	unique_ptr<formatted_message> fmsg;
	my_time_point now;
	multimap<protocol *, BYTE8>::const_iterator iter_p_a;
	map<string, string *>::const_iterator iter_t_u;
	configuration *c;

	do {
		rmsg.reset(receive_raw_message());
		if (rmsg != nullptr) {
			fmsg.reset(new(rmsg->TML) formatted_message(rmsg->TML));
			decode_message(*rmsg, *fmsg);
			now = my_now();
			remote *&r = addr_remote[fmsg->SRC];
			if (r == nullptr) {
				r = new remote;
				r->out_ID = uid(dre);
			}
			iter_t_u = table_user.find("t"s + BYTE8_to_c17charp(fmsg->DST));
			c = username_configuration[iter_t_u != table_user.cend() && iter_t_u->second != nullptr
					? *iter_t_u->second : "root"];
			if (c->use_internet_switch_algorithm) {
				map<BYTE8, my_time_point> *&iT = r->proto_iSRC_TWR[rmsg->proto];
				if (iT == nullptr) {
					iT = new map<BYTE8, my_time_point>;
				}
				my_time_point &t1 = (*iT)[rmsg->imm_addr];
				if (t1.time_since_epoch() == chrono::system_clock::duration::zero()) {
					t1 = now;
				}
			}
			map<BYTE, my_time_point> *&IT = r->DST_ID_TWR[fmsg->DST];
			if (IT == nullptr) {
				IT = new map<BYTE, my_time_point>;
			}
			my_time_point &t2 = (*IT)[fmsg->ID];
			if (chrono::duration_cast<chrono::seconds>(now - t2).count() < c->nsecs_id) {
						//SRC, DST, ID and approx. time same -> duplicate
				LOG_CPP("received duplicate message of ID " << HEX(static_cast<int>(fmsg->ID), 2)
						<< " from " << BYTE8_to_c17charp(fmsg->SRC) << endl);
				continue;
			}
			t2 = now;
			fmsg.reset(apply_rules(fmsg.release(), rmsg.get(), false));
			if (fmsg == nullptr) {
				continue;
			}
			security_check_for_receiving(*rmsg, *fmsg);
			if (fmsg->DST != local_addr) {
				for (iter_p_a = local_proto_iaddr.find(rmsg->proto);
						iter_p_a != local_proto_iaddr.cend() && iter_p_a->first == rmsg->proto
						&& iter_p_a->second != fmsg->DST; iter_p_a++)
					;
			}
			if ((fmsg->DST != local_addr && iter_p_a != local_proto_iaddr.cend() && iter_p_a->first
					== rmsg->proto && iter_p_a->second == fmsg->DST) || rmsg->broadcast) {
				if (c->forward_messages) {
					LOG_CPP("forwarding message from SRC " << BYTE8_to_c17charp(fmsg->SRC)
							<< " to DST " << BYTE8_to_c17charp(fmsg->DST) << endl);
					send_formatted_message(fmsg.release());
				}
				continue;
			}
			if (fmsg->LEN > 1) {
				THR(fmsg->is_encrypted(), message_exception("not decrypted"));
				THR(fmsg->is_signed(), message_exception("not verified"));
				LOG_CPP("received DATA/SELECT(_SUBSCRIBE)/UNSUBSCRIBE(_ALL)"
						"/ACKNOWLEDGMENT/PAYLOAD_ERROR/OPERATION_UNSUPPORTED " << *fmsg << endl);
			} else if (fmsg->LEN > 0) {
				oss.str("d=");
				oss << static_cast<int>(*fmsg->PL);
				oss.str().copy(reinterpret_cast<char *>(fmsg->PL), 5);//strlen("d=255") = 5
				fmsg->LEN = oss.tellp();
				LOG_CPP("received QUICK " << *fmsg << endl);
			} else {
				LOG_CPP("received HELLO " << *fmsg << endl);
				fmsg->DST = c->default_gateway;
				send_formatted_message(fmsg.release());
				continue;
			}
			return fmsg.release();
		}
		return nullptr;
	} while (true);
}

#ifdef OFFLINE
raw_message *receive_raw_message() {
	static const raw_message rmsgs[5] = {
		{
		c17charp_to_BYTE8(TIMES8("ab")),
		52,
		my_now(),
		protocols[3],
		false,
		false,
		const_cast<BYTE *>(reinterpret_cast<const BYTE *>("\0"
				"a,b,d,t=1.1,\'2\',123,TIMESTAMP \'2001-01-01 01:01:01\'"))
		},//everything is automatically 0 or false
		{
		c17charp_to_BYTE8(TIMES8("ab")),
		31,
		my_now() + 1s,
		protocols[3],
		false,
		false,
		nullptr,
		const_cast<BYTE *>(reinterpret_cast<const BYTE *>("\0\xDE\x85""a.d\xAC""t"
				TIMES8("\xAB") "\x87""a\xD1\x8F""a.t\x9F\xA8\xAA""1\xDA\xCF\xE6""1"))
		},
		{
		c17charp_to_BYTE8(TIMES8("ab")),
		42,
		my_now() + 2s,
		protocols[3],
		false,
		false,
		nullptr,
		const_cast<BYTE *>(reinterpret_cast<const BYTE *>("\0"
				"d,t=456e2,TIMESTAMP \'2002-02-02 02:02:02\'"))
		},
		{
		c17charp_to_BYTE8(TIMES8("ab")),
		3,
		my_now() + 3s,
		protocols[3],
		false,
		false,
		nullptr,
		const_cast<BYTE *>(reinterpret_cast<const BYTE *>("\0\xF7""1"))
		},
		{
		c17charp_to_BYTE8(TIMES8("ab")),
		0,
		my_now() + 4s,
		protocols[3],
		false,
		false,
		nullptr,
		const_cast<BYTE *>(reinterpret_cast<const BYTE *>(""))
		}
	};
	static int i = -1;
	unique_ptr<raw_message> rmsg;

	if (++i < 5) {
		this_thread::sleep_for(1s);
		rmsg.reset(new raw_message(rmsgs[i].msg));
		rmsg->TML = rmsgs[i].TML;
		rmsg->imm_addr = rmsgs[i].imm_addr;
		rmsg->broadcast = rmsgs[i].broadcast;
		rmsg->override_implicit_rules = rmsgs[i].override_implicit_rules;
		rmsg->TWR = rmsgs[i].TWR;
		rmsg->proto = rmsgs[i].proto;
		rmsg->CCF = rmsgs[i].CCF;
		rmsg->ACF = rmsgs[i].ACF;
		LOG_CPP("received " << *rmsg << endl);
		return rmsg.release();
	}
	LOG_CPP("no more raw messages" << endl);
	return nullptr;
}
#else
raw_message *receive_raw_message() {
	refresh_next_timed_rule_time_struct rntrts;
	string select;
	int message_length, current_id, i, j;
	ext_struct es;
	send_inject_struct *sis;
	timespec ts;
	raw_message *rmsg;
	unique_ptr<raw_message> dummy;
	long pid;
	rlimit rl;
	load_store_struct lss;
	load_ack_struct las;
	config_struct cs;
	PGresult *res_rules;
	stringstream ss(ss.in | ss.out | ss.ate);
	update_permissions_struct ups;
	manually_execute_timed_rule_struct metrs;
	string current_username;

	clock_gettime(CLOCK_REALTIME, &ts);
	do {
		if (mq_receive(ext_mq, reinterpret_cast<char *>(&es), sizeof(ext_struct), nullptr) < 0) {
			THR(errno != EAGAIN, system_exception("cannot receive from ext_mq"));
		} else {
			ext2(es);
		}
		do {
			if (mq_receive(prlimit_pid_mq, reinterpret_cast<char *>(&pid), sizeof(pid), nullptr)
					< 0) {
				THR(errno != EAGAIN, system_exception("cannot receive from prlimit_pid_mq"));
				break;
			} else {
				LOG_CPP("received request for RLIMIT_MSGQUEUE assertion from pid " << pid << endl);
				THR(prlimit(pid, RLIMIT_MSGQUEUE, nullptr, &rl) < 0,
						system_exception("cannot get prlimit"));
				if (rl.rlim_cur < PRLIMIT) {
					rl.rlim_cur = PRLIMIT;
					if (rl.rlim_max < PRLIMIT) {
						rl.rlim_max = PRLIMIT;
					}
					THR(prlimit(pid, RLIMIT_MSGQUEUE, &rl, nullptr) < 0,
							system_exception("cannot set prlimit"));//privileged operation!!!
				}
				THR(mq_send(prlimit_ack_mq, reinterpret_cast<char *>(&pid), sizeof(pid), 0) < 0,
						system_exception("cannot send to prlimit_ack_mq"));
				LOG_CPP("sent acknowledgment for RLIMIT_MSGQUEUE assertion to pid " << pid << endl);
			}
		} while (true);
		if (mq_receive(send_inject_mq, reinterpret_cast<char *>(&message_length),
				sizeof(send_inject_struct) - 1 + msg_MAX, nullptr) < 0) {
			THR(errno != EAGAIN, system_exception("cannot receive from send_inject_mq"));
		} else {
			sis = new(message_length) send_inject_struct(message_length);
			if (mq_receive(send_inject_mq, reinterpret_cast<char *>(sis),
					sizeof(send_inject_struct) - 1 + message_length, nullptr) < 0) {
				throw system_exception("cannot receive from send_inject_mq");
			} else {
				send_inject2(*sis);
			}
			delete sis;
		}
		if (mq_receive(load_store_mq, reinterpret_cast<char *>(&lss), sizeof(lss), nullptr) < 0) {
			THR(errno != EAGAIN, system_exception("cannot receive from load_store_mq"));
		} else if (lss.load) {
			load_store2_load();
			THR(mq_send(load_ack_mq, reinterpret_cast<char *>(&las), sizeof(las), 0) < 0,
					system_exception("cannot send to load_ack_mq"));
		} else {
			load_store2_store();
		}
		if (mq_receive(config_mq, reinterpret_cast<char *>(&cs), sizeof(cs), nullptr) < 0) {
			THR(errno != EAGAIN, system_exception("cannot receive from config_mq"));
		} else {
			config2();
		}
		if (mq_receive(refresh_next_timed_rule_time_mq, reinterpret_cast<char *>(&rntrts),
				sizeof(rntrts), nullptr) < 0) {
			THR(errno != EAGAIN,
					system_exception("cannot receive from refresh_next_timed_rule_time_mq"));
		} else {
			refresh_next_timed_rule_time2(rntrts);
		}
		if (mq_receive(update_permissions_mq, reinterpret_cast<char *>(&ups),
				sizeof(ups), nullptr) < 0) {
			THR(errno != EAGAIN,
					system_exception("cannot receive from update_permissions_mq"));
		} else {
			update_permissions2();
		}
		if (mq_receive(manually_execute_timed_rule_mq, reinterpret_cast<char *>(&metrs),
				sizeof(metrs), nullptr) < 0) {
			THR(errno != EAGAIN,
					system_exception("cannot receive from manually_execute_timed_rule_mq"));
		} else {
			manually_execute_timed_rule2(metrs);
		}
		if (next_timed_rule <= ts.tv_sec++ && next_timed_rule > 0) {
			LOG_CPP("checking for rules" << endl);
			ss.str("SELECT username, id, query_command_nothing, query_command_1, "
					"send_inject_query_command_nothing, query_command_2, proto_id, imm_addr, "
					"CCF, ACF, broadcast, override_implicit_rules, activate, deactivate, "
					"FROM rules WHERE is_active AND next_run <= ");
			ss.clear();
			ss << next_timed_rule;
			select = ss.str();
			res_rules = execcheckreturn(select + " ORDER BY username ASC, id ASC");
			for (i = 0, j = PQntuples(res_rules); i < j; i++) {
				current_username = PQgetvalue(res_rules, i, 0);
				apply_rule_beginning(res_rules, current_id, i, "timed", current_username);
				PQclear(execcheckreturn("UPDATE rules SET (last_run, next_run) = (last_run "
						"+ run_period, CAST(EXTRACT(EPOCH FROM last_run + 2 * run_period) "
						"AS BIGINT)) WHERE id = "s + PQgetvalue(res_rules, i, 1)
						+ " AND username = \'" + PQgetvalue(res_rules, i, 0) + '\''));
				apply_rule_end(res_rules, current_id, i, j, 0, select, current_username);
			}
			PQclear(res_rules);
			res_rules = execcheckreturn("SELECT MIN(next_run) FROM rules");
			ss.str(PQgetvalue(res_rules, 0, 0));
			ss.clear();
			ss >> next_timed_rule;
			LOG_CPP("next timed rule " << next_timed_rule - time(nullptr) << " seconds from now"
					<< endl);
			PQclear(res_rules);
		}
		if (mq_timedreceive(main_mq, reinterpret_cast<char *>(&rmsg), sizeof(rmsg), nullptr, &ts)
				< 0) {
			THR(errno != ETIMEDOUT, system_exception("cannot receive from main_mq"));
			continue;
		}
		dummy.reset(rmsg);
		break;
	} while (true);
	LOG_CPP("received " << *rmsg << endl);
	return dummy.release();
}
#endif /* OFFLINE */

bool check_permissions(const char *tablename, BYTE8 address) {
	map<string, string *>::const_iterator iter1 = table_user.find(tablename),
			iter2 = table_user.find("t"s + BYTE8_to_c17charp(address));
//todo simplify
	if (iter1 != table_user.cend() && iter1->second != nullptr) {
		if (iter2 != table_user.cend() && iter2->second != nullptr) {
			if (*iter1->second == *iter2->second) {
				return true;
			}
			return false;
		}
		return false;
	}
	return true;
}
//todo take regular tables into consideration
void encode_bytes_to_stream(ostream &stream, const BYTE *bytes, size_t len) {
	int i = -1;

	while (++i < static_cast<int>(len)) {
		stream << hex_to_text[bytes[i] >> 4] << hex_to_text[bytes[i] & 0x0F];
	}
}

void execute_semicolon_separated_commands(const char *preamble, formatted_message &fmsg,
		raw_message &rmsg, const char *commands_in_c) {
	string semicolon_separated_commands(commands_in_c), a_single_command, first, second;
	regex re(RE_STRING "|;|#");
	const int m[2] = { -1, 0 };

	semicolon_separated_commands += '#';
	sti iter_begin(semicolon_separated_commands.cbegin(), semicolon_separated_commands.cend(),
			re, m), iter_end;
	do {//final, empty iter_begin + 1 == iter_end is not reached
		first = iter_begin++->str();
		second = iter_begin++->str();
		if (second.front() == '\'' || second.front() == '\"') {
			a_single_command += first + second;
		} else {//';' or '#'
			a_single_command += first;
			if (a_single_command == "ENCRYPT") {
				select_message(fmsg, rmsg);
				fmsg.encrypt();
				insert_message(fmsg, rmsg);
			} else if (a_single_command == "DECRYPT") {
				select_message(fmsg, rmsg);
				fmsg.decrypt();
				insert_message(fmsg, rmsg);
			} else if (a_single_command == "SIGN") {
				select_message(fmsg, rmsg);
				fmsg.sign();
				insert_message(fmsg, rmsg);
			} else if (a_single_command == "VERIFY") {
				select_message(fmsg, rmsg);
				fmsg.verify();
				insert_message(fmsg, rmsg);
			} else {
				PQclear(execcheckreturn(preamble + a_single_command));
			}
			a_single_command.clear();
		}
	} while (second.front() != '#');
}

void decode_bytes_from_stream(istream &stream, BYTE *bytes, size_t len) {
	int i = -1;

	while (++i < static_cast<int>(len)) {
		bytes[i] = (text_to_hex[static_cast<int>(stream.get())] << 4)
				+ text_to_hex[static_cast<int>(stream.get())];
	}
}

void send_inject_from_rule(const char *message, const char *proto_id, const char *imm_addr,
		bool CCF, bool ACF, bool broadcast, bool override_implicit_rules, bool send) {
	istringstream iss(message);
	int message_length = iss.str().length() >> 1;
	send_inject_struct *sis = new(message_length) send_inject_struct(message_length);
	BYTE8 imm_addr8 = c17charp_to_BYTE8(imm_addr);

	strcpy(sis->proto_id, proto_id);
	memcpy_endian(sis->imm_addr, &imm_addr8, 8);
	sis->CCF = CCF;
	sis->ACF = ACF;
	sis->send = send;
	sis->message_length = message_length;
	sis->broadcast = broadcast;
	sis->override_implicit_rules = override_implicit_rules;
	decode_bytes_from_stream(iss, sis->message_content, message_length);
	int fd = open("/tmp/flock_cpp", O_WRONLY | O_CREAT);
	THR(fd < 0, system_exception("cannot open fd"));
	THR(flock(fd, LOCK_EX) != 0, system_exception("cannot lock fd"));
	THR(mq_send(send_inject_mq, reinterpret_cast<char *>(&message_length), sizeof(message_length),
			0) < 0, system_exception("cannot send to send_inject_mq"));
	THR(mq_send(send_inject_mq, reinterpret_cast<char *>(sis), sizeof(send_inject_struct) - 1
			+ message_length, 0) < 0, system_exception("cannot send to send_inject_mq"));
	THR(close(fd) < 0, system_exception("cannot close fd"));
	delete sis;
}

void apply_rule_beginning(PGresult *res_rules, int &current_id, int i, const char *type,
		string &current_username) {
	istringstream iss(PQgetvalue(res_rules, i, 1));

	iss >> current_id;
	LOG_CPP("applying " << type << " rule " << current_id << " for username "
			<< current_username << endl);
}

void insert_message(const formatted_message &fmsg, const raw_message &rmsg) {
	ostringstream oss("INSERT INTO formatted_message_for_send_receive("
			"HD, ID, LEN, DST, SRC, PL, CRC, ENCRYPTED, SIGNED, BROADCAST, OVERRIDE, proto, "
			"imm_addr, CCF, ACF) VALUES(E\'\\\\x", oss.out | oss.ate);

	oss << HEX_NOSHOWB(static_cast<int>(fmsg.HD.get_as_byte()), 2) << "\', E\'\\\\x"
			<< HEX_NOSHOWB(static_cast<int>(fmsg.ID), 2) << "\', E\'\\\\x"
			<< HEX_NOSHOWB(fmsg.LEN, 4) << "\', E\'\\\\x" << BYTE8_to_c17charp(fmsg.DST)
			<< "\', E\'\\\\x" << BYTE8_to_c17charp(fmsg.SRC) << "\', E\'\\\\x";
	encode_bytes_to_stream(oss, fmsg.PL, fmsg.LEN);
	oss << "\', E\'\\\\x" << HEX_NOSHOWB(fmsg.CRC, 8) << "\', "
			<< BOOLALPHA_UPPERCASE(fmsg.is_encrypted()) << ", "
			<< BOOLALPHA_UPPERCASE(fmsg.is_signed()) << ", " << BOOLALPHA_UPPERCASE(rmsg.broadcast)
			<< ", " << BOOLALPHA_UPPERCASE(rmsg.override_implicit_rules) << ", \'"
			<< rmsg.proto->get_my_id() << "\', E\'\\\\x" << BYTE8_to_c17charp(rmsg.imm_addr)
			<< "\', " << BOOLALPHA_UPPERCASE(rmsg.CCF) << ", " << BOOLALPHA_UPPERCASE(rmsg.ACF)
			<< ')';
	PQclear(execcheckreturn(oss.str()));
}

formatted_message *apply_rules(formatted_message *fmsg, raw_message *rmsg, bool send) {
	auto t_u = table_user.find("t"s + BYTE8_to_c17charp(send ? fmsg->SRC : fmsg->DST));
	string current_username(t_u != table_user.cend() && t_u->second != nullptr
			? *t_u->second : "root"), select((send
			? "SELECT username, id, send_receive_seconds, filter, drop_modify_nothing, "
			"modification, query_command_nothing, query_command_1, "
			"send_inject_query_command_nothing, query_command_2, proto_id, imm_addr, CCF, ACF, "
			"broadcast, override_implicit_rules, activate, deactivate, is_active FROM rules "
			"WHERE send_receive_seconds = 0 AND is_active AND username = \'"
			: "SELECT username, id, send_receive_seconds, filter, drop_modify_nothing, "
			"modification, query_command_nothing, query_command_1, "
			"send_inject_query_command_nothing, query_command_2, proto_id, imm_addr, CCF, ACF, "
			"broadcast, override_implicit_rules, activate, deactivate, is_active FROM rules "
			"WHERE send_receive_seconds = 1 AND is_active AND username = \'")
			+ current_username + '\'');
	PGresult *res_fields, *res_rules;
	int i = 0, j, current_id;
	bool to_delete = false;
	const char *value;
	unique_ptr<formatted_message> dummy(fmsg);

	insert_message(*fmsg, *rmsg);

	res_rules = execcheckreturn(select + " ORDER BY id ASC");
	j = PQntuples(res_rules);
	while (++i < j && !to_delete) {
		res_fields = execcheckreturn("SELECT "s + PQgetvalue(res_rules, i, 3)
				+ " FROM formatted_message_for_send_receive");
		if (PQntuples(res_fields) == 1 && PQnfields(res_fields) == 1
				&& strcmp(PQgetvalue(res_fields, 0, 0), "t") == 0) {
			apply_rule_beginning(res_rules, current_id, i,
					send ? "send" : "receive", current_username);
			value = PQgetvalue(res_rules, i, 4);
			if (*value == '0') {
				LOG_CPP("marking for deletion" << endl);
				to_delete = true;
			} else if (*value == '1') {
				LOG_CPP("modifying message" << endl);
				execute_semicolon_separated_commands("UPDATE formatted_message_for_send_receive "
						"SET ", *fmsg, *rmsg, PQgetvalue(res_rules, i, 5));
			}
			apply_rule_end(res_rules, current_id, i, j, 3, select, current_username);
		}
		PQclear(res_fields);
	}
	PQclear(res_rules);

	select_message(*fmsg, *rmsg);

	if (to_delete) {
		LOG_CPP("deleting message" << endl);
		return nullptr;
	}
	return dummy.release();
}

void select_message(formatted_message &fmsg, raw_message &rmsg) {
	PGresult *res = execcheckreturn("SELECT HD, ID, LEN, DST, SRC, PL, CRC, BROADCAST, OVERRIDE, "
			"proto, imm_addr, CCF, ACF FROM formatted_message_for_send_receive");
	istringstream iss(PQgetvalue(res, 0, 0) + 2);
	int i;

	iss >> HEX_INP(i);
	fmsg.HD.put_as_byte(static_cast<BYTE>(i));
	iss.str(PQgetvalue(res, 0, 1) + 2);
	iss.clear();
	iss >> HEX_INP(i);
	fmsg.ID = i;
	iss.str(PQgetvalue(res, 0, 2) + 2);
	iss.clear();
	iss >> HEX_INP(fmsg.LEN);
	fmsg.DST = c17charp_to_BYTE8(PQgetvalue(res, 0, 3) + 2);
	fmsg.SRC = c17charp_to_BYTE8(PQgetvalue(res, 0, 4) + 2);
	iss.str(PQgetvalue(res, 0, 5) + 2);
	iss.clear();
	decode_bytes_from_stream(iss, fmsg.PL, fmsg.LEN);
	iss.str(PQgetvalue(res, 0, 6) + 2);
	iss.clear();
	iss >> HEX_INP(fmsg.CRC);
	rmsg.broadcast = *PQgetvalue(res, 0, 7) == 't';
	rmsg.override_implicit_rules = *PQgetvalue(res, 0, 8) == 't';
	rmsg.proto = find_protocol_by_id(PQgetvalue(res, 0, 9));
	rmsg.imm_addr = c17charp_to_BYTE8(PQgetvalue(res, 0, 10) + 2);
	rmsg.CCF = *PQgetvalue(res, 0, 11) == 't';
	rmsg.ACF = *PQgetvalue(res, 0, 12) == 't';
	PQclear(res);
	PQclear(execcheckreturn("TRUNCATE TABLE formatted_message_for_send_receive"));
}

//todo check permissions
void apply_rule_end(PGresult *&res_rules, int current_id, int &i, int &j, int offset,
		string &select, string &current_username) {
	int new_id;
	stringstream ss(ss.in | ss.out | ss.ate);
	bool reset = false;
	PGresult *res_message;
	const char *value = PQgetvalue(res_rules, i, offset + 2);

	if (*value == '0') {
		PQclear(execcheckreturn(PQgetvalue(res_rules, i, offset + 3)));
	} else if (*value == '1') {
		ss.str("COPY (SELECT) TO PROGRAM \'bash -c \"");
		PQclear(execcheckreturn(ss.str() + PQgetvalue(res_rules, i, offset + 3) + "\"\'"));
	}
	value = PQgetvalue(res_rules, i, offset + 4);
	if (*value == '0' || *value == '2') {
		ss.str("INSERT INTO raw_message_for_query_command(message) ");
		PQclear(execcheckreturn(ss.str() + PQgetvalue(res_rules, i, offset + 5)));
	} else if (*value == '1' || *value == '3') {
		ss.str("COPY raw_message_for_query_command(message) FROM PROGRAM \'bash -c \"");
		PQclear(execcheckreturn(ss.str() + PQgetvalue(res_rules, i, offset + 5) + "\"\'"));
	}
	res_message = execcheckreturn("SELECT message FROM raw_message_for_query_command");
	if (*value <= '1') {
		send_inject_from_rule(PQgetvalue(res_message, 0, 0),
				PQgetvalue(res_rules, i, offset + 6), PQgetvalue(res_rules, i, offset + 7) + 2,
				*PQgetvalue(res_rules, i, offset + 8) == 't',
				*PQgetvalue(res_rules, i, offset + 9) == 't',
				*PQgetvalue(res_rules, i, offset + 10) == 't',
				*PQgetvalue(res_rules, i, offset + 11) == 't', true);
	} else if (*value <= '4') {
		send_inject_from_rule(PQgetvalue(res_message, 0, 0),
				PQgetvalue(res_rules, i, offset + 6), PQgetvalue(res_rules, i, offset + 7) + 2,
				*PQgetvalue(res_rules, i, offset + 8) == 't',
				*PQgetvalue(res_rules, i, offset + 9) == 't',
				*PQgetvalue(res_rules, i, offset + 10) == 't',
				*PQgetvalue(res_rules, i, offset + 11) == 't',
				false);
	}
	PQclear(res_message);
	PQclear(execcheckreturn("TRUNCATE TABLE raw_message_for_query_command"));
	value = PQgetvalue(res_rules, i, offset + 12);
	if (*value != '\0') {
		LOG_CPP("activating rule " << value << endl);
		PQclear(execcheckreturn("UPDATE rules SET is_active = TRUE WHERE id = "s + value
				+ " AND username = \'" + current_username + '\''));
		ss.str(value);
		ss >> new_id;
		if (new_id > current_id) {
			LOG_CPP("marking rules for refreshing" << endl);
			reset = true;
		}
	}
	value = PQgetvalue(res_rules, i, offset + 13);
	if (*value != '\0') {
		LOG_CPP("deactivating rule " << value << endl);
		PQclear(execcheckreturn("UPDATE rules SET is_active = FALSE WHERE id = "s + value
				+ " AND username = \'" + current_username + '\''));
		ss.str(value);
		ss.clear();
		ss >> new_id;
		if (new_id > current_id) {
			LOG_CPP("marking rules for refreshing" << endl);
			reset = true;
		}
	}
	if (reset) {
		LOG_CPP("refreshing rules" << endl);
		PQclear(res_rules);
		ss.str(select);
		ss.clear();
		ss << " AND id > " << current_id << " AND username = \'" << current_username
				<< "\' ORDER BY id ASC";
		res_rules = execcheckreturn(ss.str());
		j = PQntuples(res_rules);
		i = -1;
	}
}

BYTE8 c17charp_to_BYTE8(const char *address) {
	int i = -1;
	BYTE8 ret = 0x00000000'00000000;

	THR(address[16] != '\0', system_exception("error in c17charp_to_BYTE8"));
	while (++i < 16) {//does not depend on endianness
		ret = (ret << 4) + text_to_hex[static_cast<int>(address[i])];
	}
	return ret;
}

ostream &operator<<(ostream &os, const raw_message &rmsg) noexcept {
	os << "raw message of TML " << rmsg.TML << " by imm_addr " << BYTE8_to_c17charp(rmsg.imm_addr)
			<< " with payload \"";
	print_message_c(os, rmsg.msg, rmsg.TML);
	os << "\", relative time "
			<< chrono::duration_cast<chrono::seconds>(rmsg.TWR - beginning).count()
			<< " using protocol " << *rmsg.proto << " on a ";
	if (!rmsg.CCF) {
		os << "non-";
	}
	os << "confidential and ";
	if (!rmsg.ACF) {
		os << "non-";
	}
	return os << "authentic channel, broadcast = " << BOOLALPHA_UPPERCASE(rmsg.broadcast)
			<< ", override_implicit_rules = " << BOOLALPHA_UPPERCASE(rmsg.override_implicit_rules);
}

void manually_execute_timed_rule2(const manually_execute_timed_rule_struct &metrs) {
	ostringstream oss("SELECT username, id, query_command_nothing, query_command_1, "
			"send_inject_query_command_nothing, query_command_2, proto_id, imm_addr, CCF, ACF, "
			"broadcast, override_implicit_rules, activate, deactivate, is_active, FROM rules "
			"WHERE id = ");
	PGresult *res_rules;
	string select, current_username(metrs.username);
	int i, j = 1;

	oss << metrs.id << " AND username = \'" << current_username << '\'';
	select = oss.str();
	res_rules = execcheckreturn(select);
	apply_rule_beginning(res_rules, i, 0, "timed", current_username);
	apply_rule_end(res_rules, metrs.id, i, j, 0, select, current_username);
	PQclear(res_rules);
}

void print_message_c(ostream &os, const BYTE *msg, size_t length) noexcept {
	int i = -1;
	const char table[17] = "0123456789ABCDEF";

	while (++i < static_cast<int>(length)) {//coded characters are surely not the following
		switch (msg[i]) {
		case '\\':
			os << "\\\\";
			break;
		case '\'':
			os << "\\\'";
			break;
		case '\"':
			os << "\\\"";
			break;
		case '\?':
			os << "\\\?";
			break;
		case '\a':
			os << "\\a";
			break;
		case '\b':
			os << "\\b";
			break;
		case '\f':
			os << "\\f";
			break;
		case '\n':
			os << "\\n";
			break;
		case '\r':
			os << "\\r";
			break;
		case '\t':
			os << "\\t";
			break;
		case '\v':
			os << "\\v";
			break;
		case '\0':
			os << "\\0";
			break;
		default://disregarding control characters
			if (isprint(msg[i])) {
				os << msg[i];
			} else {
				os << "\\x" << table[msg[i] >> 4] << table[msg[i] & 0x0F];
			}
		}
	}
}

ostream &operator<<(ostream &os, const protocol &proto) noexcept {
	return os << proto.get_my_id();
}

void *memcpy_endian(void *dst, const void *src, size_t size) noexcept {
	return little_endian ? memcpy_reverse(dst, src, size) : memcpy(dst, src, size);
}

void *memcpy_reverse(void *dst, const void *src, size_t size) noexcept {
	int i = size--;

	while (--i >= 0) {
		static_cast<BYTE *>(dst)[size - i] = static_cast<const BYTE *>(src)[i];
	}
	return dst;
}

BYTE4 givecrc32c(const BYTE *msg, BYTE2 len) noexcept {
	int maxB4 = ceil(len / 4.), msB4, i = len, msb, lenB4 = maxB4 + 1;//msb = most significant bit
	BYTE4 *temp = new BYTE4[lenB4], ret;
	BYTE8 polynome = 0x00000001'1EDC6F41;//0x1EDC6F41 = 0b0001'1110'1101'1100'0110'1111'0100'0001

	if (little_endian) {
		msB4 = lenB4;
		temp[maxB4] = 0x00000000;
		//current memory layout (example): msg = B9 B8 B7 B6 B5 B4 B3 B2 B1 B0
		while (--i >= 0) {
			*(reinterpret_cast<BYTE *>(temp) + i + 4) = header::reverse_byte(msg[len - i - 1]);
			//data is reversed by byte and bit in byte
		}//current memory layout (ex.): temp = xx xx xx xx 0B 1B 2B 3B 4B 5B 6B 7B 8B 9B 00 00
		*reinterpret_cast<BYTE4 *>(reinterpret_cast<BYTE *>(temp) + len) ^= 0xFFFFFFFF;
		*temp = 0x00000000;//00 00 00 00 0B 1B 2B 3B 4B 5B 6B' 7B' 8B' 9B' 00 00
		do {
			do {
				//00 00 9B' 8B' is the first thing put into 32-bit register
				for (msb = 31, msB4--; msb >= 0 && (temp[msB4] & (0x00000001 << msb)) == 0; msb--)
					;
			} while (msB4 > 0 && msb < 0);
			if (msB4 > 0) {
				//00 00 9B' 8B' 7B' 6B' 5B 4B is the first thing put into 64-bit register
				*reinterpret_cast<BYTE8 *>(temp + msB4++ - 1) ^= polynome << msb;
						//unaligned pointer!
			}
		} while (msB4 > 0);
		for (i = 3; i >= 0; i--) {
			*(reinterpret_cast<BYTE *>(&ret) + 3 - i)
					= header::reverse_byte(*(reinterpret_cast<BYTE *>(temp + msB4) + i));
		}
	} else {
		msB4 = -1;
		*temp = 0x00000000;
		//current memory layout (example): msg = B9 B8 B7 B6 B5 B4 B3 B2 B1 B0
		while (--i >= 0) {
			*(reinterpret_cast<BYTE *>(temp) + (lenB4 << 2) - len + i)
					= header::reverse_byte(msg[i]);
			//data is aligned right 4B and reversed by bit
		}//current memory layout (ex.): temp = 00 00 0B 1B 2B 3B 4B 5B 6B 7B 8B 9B xx xx xx xx
		*reinterpret_cast<BYTE4 *>(reinterpret_cast<BYTE *>(temp) + (lenB4 << 2) - len)
				^= 0xFFFFFFFF;
		temp[maxB4] = 0x00000000;//00 00 0B 1B 2B 3B 4B 5B 6B' 7B' 8B' 9B' 00 00 00 00
		do {
			do {
				//again 00 00 9B' 8B' first thing into 32-bit
				for (msb = 31, msB4++; msb >= 0 && (temp[msB4] & (0x00000001 << msb)) == 0; msb--)
					;
			} while (msB4 < maxB4 && msb < 0);
			if (msB4 < maxB4) {
				//again 00 00 9B' 8B' 7B' 6B' 5B 4B first thing into 64-bit
				*reinterpret_cast<BYTE8 *>(temp + msB4--) ^= polynome << msb;//unaligned pointer!
			}
		} while (msB4 < maxB4);
		for (i = 3; i >= 0; i--) {
			*(reinterpret_cast<BYTE *>(&ret) + i)
					= header::reverse_byte(*(reinterpret_cast<BYTE *>(temp + msB4) + i));
		}
	}
	delete[] temp;
	return ret ^ 0xFFFFFFFF;
}

ostream &operator<<(ostream &os, const formatted_message &fmsg) noexcept {
	os << "formatted message of HD " << fmsg.HD
			<< ", ID " << HEX(static_cast<int>(fmsg.ID), 2)
			<< " and LEN " << fmsg.LEN
			<< " for DST " << BYTE8_to_c17charp(fmsg.DST)
			<< " from SRC " << BYTE8_to_c17charp(fmsg.SRC) << " with payload \"";
	print_message_c(os, fmsg.PL, fmsg.LEN);
	return os << "\" and CRC " << HEX(fmsg.CRC, 8) << " (encrypted = "
			<< BOOLALPHA_UPPERCASE(fmsg.is_encrypted()) << ") (signed = "
			<< BOOLALPHA_UPPERCASE(fmsg.is_signed()) << ')';
}

ostream &operator<<(ostream &os, const header &HD) noexcept {
	return os << HEX(static_cast<int>(HD.get_as_byte()), 2) << " ("
			<< (HD.I ? 'I' : 'i') << (HD.L ? 'L' : 'l')
			<< (HD.D ? 'D' : 'd') << (HD.S ? 'S' : 's')
			<< (HD.R ? 'R' : 'r') << (HD.K ? 'K' : 'k')
			<< (HD.C ? 'C' : 'c') << (HD.A ? 'A' : 'a') << ')';
}

void convert_select(string &query, string remote_FROM) {
	const char *table[128] = {
			//0x80-0x87:
			" <= ", " >= ", " <> ", " || ",
			" AND ", " ALL ", " ANY ", " AS ",
			//0x88-0x8F:
			" ASC ", " ASYMMETRIC ", " AT ", " AVG ",
			" BETWEEN ", " BERNOULLI ", " BREADTH ", " BY ",
			//0x90-0x97:
			" CASE ", " COALESCE ", " COLLATE ", " CORRESPONDING ",
			" COUNT ", " CROSS ", " CUBE ", " CURRENT_DATE ",
			//0x98-0x9F:
			" CURRENT_TIME ", " CURRENT_TIMESTAMP ", " CYCLE ", " DATE ",
			" DAY ", " DEFAULT ", " DEPTH ", " DESC ",
			//0xA0-0xA7:
			" DISTINCT ", " ELSE ", " ESCAPE ", " END ",
			" EVERY ", " EXCEPT ", " EXISTS ", " FALSE ",
			//0xA8-0xAF:
			" FETCH ", " FILTER ", " FIRST ", " FLAG ",
			" FROM ", " FULL ", " GROUP ", " GROUPING ",
			//0xB0-0xB7:
			" HAVING ", " HOUR ", " IN ", " INNER ",
			" INTERSECT ", " INTERVAL ", " IS ", " JOIN ",
			//0xB8-0xBF:
			" LAST ", " LATERAL ", " LEFT ", " LIKE ",
			" LIKE_REGEX ", " LOCAL ", " LOCALTIME ", " LOCALTIMESTAMP ",
			//0xC0-0xC7:
			" MATCH ", " MAX ", " MIN ", " MINUTE ",
			" MONTH ", " NATURAL ", " NEXT ", " NORMALIZED ",
			//0xC8-0xCF:
			" NOT ", " NULL ", " NULLIF ", " NULLS ",
			" OF ", " OFFSET ", " ON ", " ONLY ",
			//0xD0-0xD7:
			" OR ", " ORDER ", " OUTER ", " OVERLAPS ",
			" PARTIAL ", " PERCENT ", " RECURSIVE ", " REPEATABLE ",
			//0xD8-0xDF:
			" RIGHT ", " ROLLUP ", " ROW ", " ROWS ",
			" SEARCH ", " SECOND ", " SELECT ", " SET ",
			//0xE0-0xE7:
			" SETS ", " SIMILAR ", " SIMPLE ", " SOME ",
			" STDDEV_POP ", " STDDEV_SAMP ", " SUBSCRIBE ", " SUM ",
			//0xE8-0xEF:
			" SYMMETRIC ", " SYSTEM ", " TABLE ", " TABLESAMPLE ",
			" THEN ", " TIES ", " TIME ", " TIMESTAMP ",
			//0xF0-0xF7:
			" TO ", " TRUE ", " UESCAPE ", " UNION ", " UNIQUE ",
			" UNKNOWN ", " USING ", " UNSUBSCRIBE ",
			//0xF8-0xFF:
			" VALUES ", " VAR_POP ", " VAR_SAMP ", " WHEN ",
			" WHERE ", " WITH ", " YEAR ", " ZONE "
	};
	string temp, iter_split_str, iter_other_str;
	BYTE8 addr;
	/*
	 * deliberately not ignoring case in re_select
	 * deliberately not ignoring case in re_other
	 * between SELECT and FROM singleton sub-queries can exist
	 * SUBSCRIBE is at the end of the query
	 */
	regex re_split(RE_STRING "|#"), re_select("\\bSELECT\\b"),
			re_other("\\b(FROM|WHERE|GROUP|HAVING|ORDER|FETCH|OFFSET"
			"|UNION|INTERSECT|EXCEPT|SUBSCRIBE)\\b|\\(|\\)", regex_constants::nosubs),
			re_unique("\\bUNIQUE\\b", regex_constants::nosubs | regex_constants::icase),
			re_corresp("\\bCORRESPONDING\\b", regex_constants::nosubs | regex_constants::icase),
			re_match("\\bMATCH\\b", regex_constants::nosubs | regex_constants::icase),
			re_ties("\\bTIES\\b", regex_constants::nosubs | regex_constants::icase),
			re_when("\\bWHEN\\b", regex_constants::nosubs | regex_constants::icase),
			re_then("\\bTHEN\\b|,|\\(|\\)", regex_constants::nosubs | regex_constants::icase);
	const int m[2] = { -1, 0 };
	int rel_level, i = -1, j = query.length();
	bool search_select = true, search_when = true;
	string::const_iterator iter_search_from, iter_search_to;
	char fr;

	/* converting */
	LOG_CPP("converting SELECT \"");
	LOG_CP(print_message_cpp, query);
	while (++i < j) {
		if ((query[i] == '\xAC' || query[i] == '\xB7') && i + 9 < j
				&& query[i + 1] == 't') {//"\xACt" or "\xB7t" imply a short address
			memcpy_endian(&addr, &query[i + 2], 8);
			temp = temp + table[query[i] + 128] + 't' + BYTE8_to_c17charp(addr);
			i += 9;
		} else if (query[i] < 0) {//cannot be shortened using ? :
			temp += table[query[i] + 128];
		} else {
			temp += query[i];
		}
	}
	LOG_CPP("\" to  \"" << temp << ";\"" << endl);

	/* checking for unsupported and missing FROMs */
	temp += '#';
	query.clear();
	sti iter_split(temp.begin(), temp.end(), re_split, m), iter_end;
	do {//final, empty (iter_split + 1) == iter_end is not reached
		iter_split_str = iter_split++->str();
		THR(regex_search(iter_split_str, re_unique), unsupported_exception("F291 not supported"));
		THR(regex_search(iter_split_str, re_corresp), unsupported_exception("F301 not supported"));
		THR(regex_search(iter_split_str, re_match), unsupported_exception("F741 not supported"));
		THR(regex_search(iter_split_str, re_ties), unsupported_exception("F867 not supported"));

		/* checking for potential F263 */
		iter_search_from = iter_split_str.begin();
		iter_search_to = iter_split_str.end();
		do {
			if (search_when) {
				/* searching for beginning of WHEN list ("WHEN") */
				sti iter_when(iter_search_from, iter_search_to, re_when, 0);
				if (iter_when == iter_end) {//no beginning found, go to next token
					break;
				}//beginning found, continue with left-trimmed (same) token
				iter_search_from = iter_when->second;
				search_when = false;
				rel_level = 0;
			} else {
				/*
				 * searching for end of WHEN list ("THEN"),
				 * F263 (zero-level ','),
				 * or nesting ('(' or ')')
				 */
				sti iter_then(iter_search_from, iter_search_to, re_then, 0);
				while (iter_then != iter_end) {
					fr = iter_then++->str().front();
					if (fr == '(') {
						/* found '(' */
						rel_level++;
					} else if (fr == 'T') {
						/* found "THEN" */
						iter_search_from = iter_then->second;
						search_when = true;
						break;
					} else if (fr == ',' && rel_level == 0) {
						/* found zero-level ',' */
						throw unsupported_exception("F263 not supported");
					} else {
						/* found ')' */
						rel_level--;
					}
				}
				if (iter_then == iter_end) {
					/* token over without end of WHEN list; go to next one */
					break;
				}
			}
		} while (true);

		/* checking for missing FROMs */
		iter_search_from = iter_split_str.begin();
		iter_search_to = iter_split_str.end();
		do {
			if (search_select) {
				/* searching for beginning of SELECT list ("SELECT") */
				sti iter_select(iter_search_from, iter_search_to, re_select, 0);
				if (iter_select == iter_end) {//no beginning found, next token; append current
					query.append(iter_search_from, iter_search_to);
					break;
				}//beginning found, continue with left-trimmed same token; append trim
				query.append(iter_search_from, iter_select->first);
				iter_search_from = iter_select->first;
				search_select = false;
				rel_level = 0;
			} else {
				/*
				 * searching for end of SELECT list (
				 * 		"FROM", "WHERE", "GROUP", "HAVING", "ORDER", "FETCH", "OFFSET", "UNION",
				 * 		"INTERSECT", "EXCEPT", "SUBSCRIBE", or zero-level ')'
				 * ) or nesting ('(' or non-zero-level ')')
				 */
				sti iter_other(iter_search_from, iter_search_to, re_other, 0);
				while (iter_other != iter_end) {
					iter_other_str = iter_other->str();
					fr = iter_other_str.front();
					if (fr == '(') {
						/* found '(' */
						rel_level++;
					} else if (fr == 'F' && iter_other_str[1] == 'R') {
						/* found "FROM" */
						query.append(iter_search_from, iter_other->second);
						for (iter_search_from = iter_other->second;
								iter_search_from != iter_search_to && isspace(*iter_search_from);
								iter_search_from++) {
							/* go to the end of FROM clause */
							query += *iter_search_from;
						}
						if (iter_search_from == iter_search_to || *iter_search_from == ')'
								|| isupper(*iter_search_from)) {
							/* FROM clause ended abruptly and no table name provided */
							query += remote_FROM;
							LOG_CPP("remote FROM inserted" << endl);
						}
						search_select = true;
						break;
					} else if (fr != ')' || --rel_level < 0) {
						/*
						 * found
						 * 		"WHERE", "GROUP", "HAVING", "ORDER", "FETCH", "OFFSET",
						 * 		"UNION", "INTERSECT", "EXCEPT", "SUBSCRIBE", or zero-level ')'
						 * and no FROM clause provided;
						 * insert local FROM
						 */
						query.append(iter_search_from, iter_other->first);
						query += local_FROM;
						LOG_CPP("local FROM inserted" << endl);
						iter_search_from = iter_other->first;
						search_select = true;
						break;
					}
				}
				if (iter_other == iter_end) {
					/* token over without end of SELECT list; go to next one */
					query.append(iter_search_from, iter_search_to);
					break;
				}
			}
		} while (true);
		iter_split_str = iter_split++->str();
		if (iter_split_str.back() == '#') {
			if (!search_select) {
				/*
				 * end of SELECT list found at the end of string;
				 * no FROM clause provided;
				 * insert local FROM
				 */
				query += local_FROM;
				LOG_CPP("local FROM inserted" << endl);
			}
			break;
		}
		if (query.back() == 'X') {
			query.pop_back();
			query += "E\'\\\\x" + iter_split_str.substr(1);
		} else {
			query += iter_split_str;
		}
	} while (true);
	//not portable changes:
	//F263: Comma-separated predicates in simple CASE expression
	//F291: UNIQUE predicate
	//F301: CORRESPONDING in query expressions
	//F741: Referential MATCH types
	//F841: LIKE_REGEX predicate
	//F846: Octet support in regular expression operators
	//F847: Nonconstant regular expressions
	//F867: FETCH FIRST clause: WITH TIES option
}

void print_message_cpp(ostream &os, string msg) noexcept {
	print_message_c(os, reinterpret_cast<const BYTE *>(msg.c_str()), msg.length());
}

void format_select(string &query) {
	regex re_split(RE_STRING), re_whitesp("\\s+"), re_extrasp("\\b \\B|\\B \\b||\\B \\B");
	const int m[2] = { -1, 0 };
	string temp;
	sti iter_begin(query.begin(), query.end(), re_split, m), iter_end;

	LOG_CPP("formatting SELECT \"" << query);
	do {
		temp += regex_replace(regex_replace(iter_begin->str(), re_whitesp, " "), re_extrasp, "");
		if (++iter_begin == iter_end) {
			break;
		}
		temp += iter_begin->str();
	} while (++iter_begin != iter_end);
	query = temp;
	LOG_CPP(";\" to \"" << query << ";\"" << endl);
}

void sub(string query, string _id, BYTE8 address) {
	string addr_id(BYTE8_to_c17charp(address) + _id);
	regex re("(?:FROM|JOIN) +([a-z0-9]+)");//deliberately not ignoring case
	sti iter_begin(query.begin(), query.end(), re, 1), iter_end;

	unsub(_id, address);
	sel(query, address);
	if (iter_begin != iter_end) {
		PQclear(execcheckreturn("CREATE TABLE table_" + addr_id + " AS " + query));
		PQclear(execcheckreturn("CREATE VIEW view_" + addr_id + " AS " + query));
		/*
		 * in SQL standard this function could be in-lined into the trigger definition
		 * because procedural-style programming is supported using "BEGIN ATOMIC";
		 * in PostgreSQL the function must be written separately
		 * because only "EXECUTE PROCEDURE" is supported
		 *
		 * this could be transformed into non-procedural SQL
		 * because PostgreSQL supports INSERT, UPDATE and DELETE in sub-queries,
		 * but its trigger functions still have to PLPGSQL so it would be useless
		 */
		PQclear(execcheckreturn("CREATE FUNCTION function_" + addr_id
				+ "() RETURNS trigger AS \'BEGIN IF EXISTS(TABLE view_" + addr_id
				+ " EXCEPT ALL TABLE table_" + addr_id + ") THEN TRUNCATE table_" + addr_id
				+ "; INSERT INTO table_" + addr_id + " TABLE view_" + addr_id + "; PERFORM ext(\'\'"
				+ addr_id + "\'\'); END IF; RETURN NULL; END;\' LANGUAGE PLPGSQL"));

		do {
			/*
			 * in SQL standard every trigger must be created separately
			 *
			 * also, TRUNCATE triggers do not exist in SQL standard
			 */
			PQclear(execcheckreturn("CREATE TRIGGER trigger_" + addr_id + '_'
					+ iter_begin->str().substr(1)
					+ " AFTER INSERT OR UPDATE OR DELETE OR TRUNCATE ON " + iter_begin->str()
					+ " EXECUTE PROCEDURE function_" + addr_id + "()"));
		} while (++iter_begin != iter_end);
	}
}

void unsub(string _id, BYTE8 address) {
	string addr_id(BYTE8_to_c17charp(address) + _id);

	PQclear(execcheckreturn("DROP TABLE IF EXISTS table_" + addr_id));
	PQclear(execcheckreturn("DROP VIEW IF EXISTS view_" + addr_id));
	/*
	 * in SQL standard only one parameterized function function(addr_id) could be defined
	 * because triggers can be dropped without knowing their tables;
	 * in PostgreSQL many unparameterized functions function_addr_id() must be defined
	 * because triggers cannot be dropped without knowing them
	 *
	 * IF EXISTS is not supported by the SQL standard (not here or anywhere before),
	 * in the SQL standard the database response would have to be checked
	 * (and possible error then discarded)
	 */
	PQclear(execcheckreturn("DROP FUNCTION IF EXISTS function_" + addr_id + " CASCADE"));
}

void sel(string query, BYTE8 address) {
	regex re("(?:FROM|JOIN) +([a-z0-9]+)", regex_constants::icase);//must ignore case for security

	/*
	 * is not SELECT if doesn't start with "SELECT\\b",
	 * "WITH\\b", "TABLE\\b", or '('s before those
	 */
	THR(!is_select(query), error_exception("error in query"));
	/*
	 * is not clean if contains INSERT/UPDATE/DELETE in pre-queries,
	 * multi-queries, SELECT INTO, or SELECT FOR
	 */
	THR(!clean_select(query), error_exception("error in SELECT"));
	for (sti iter_begin(query.begin(), query.end(), re, 1), iter_end; iter_begin != iter_end;
			iter_begin++) {
		THR(!check_permissions(iter_begin->str().c_str(), address),
				error_exception("unauthorized"));
	}
	PQclear(formatsendreturn(execcheckreturn(query), address));
}

bool is_select(string query) {
	regex re("\\(*(SELECT|WITH|TABLE)\\b(.|\\n|\\r)*", regex_constants::nosubs);
			//deliberately not ignoring case

	return regex_match(query, re);
}

bool clean_select(string query) {
	regex re_split(RE_STRING), re_search("INSERT|UPDATE|DELETE|;|INTO|FOR",
			regex_constants::icase);//must ignore case for security
	sti iter_begin(query.begin(), query.end(), re_split, -1), iter_end;

	do {
		if (regex_search(iter_begin->str(), re_search)) {
			return false;
		}
	} while (++iter_begin != iter_end);
	return true;
}

PGresult *formatsendreturn(PGresult *res, BYTE8 DST) {
	int i = -1, j = PQnfields(res), k, l;
	string data;
	unique_ptr<formatted_message> fmsg;
	PQprintOpt opt = { 0 };
	map<BYTE8, remote *>::const_iterator iter = addr_remote.find(DST);
	const char *value;
	BYTE2 LEN;

	opt.header = 1;
	opt.align = 1;
	opt.fieldSep = const_cast<char *>("|");
	LOG_CPP("formatting " << endl);
	LOG_C(PQprint, res, &opt);
	while (++i < j) {
		data = data + PQfname(res, i) + ',';
	}
	data.back() = '=';
	for (k = 0, l = PQntuples(res); k < l; k++) {
		for (i = 0; i < j; i++) {
			if (PQgetisnull(res, k, i) != 0) {
				data += "NULL";
			} else {
				switch (PQftype(res, i)) {
				case NUMERICOID:
					data += PQgetvalue(res, k, i);
					break;
				case INTERVALOID:
					data = data + "INTERVAL \'" + PQgetvalue(res, k, i) + '\'';
					break;
				case TIMESTAMPTZOID:
				case TIMESTAMPOID:
					data = data + "TIMESTAMP \'" + PQgetvalue(res, k, i) + '\'';
					break;
				case DATEOID:
					data = data + "DATE \'" + PQgetvalue(res, k, i) + '\'';
					break;
				case TIMEOID:
				case TIMETZOID:
					data = data + "TIME \'" + PQgetvalue(res, k, i) + '\'';
					break;
				case TEXTOID:
					data = data + '\'' + PQgetvalue(res, k, i) + '\'';
					break;
				case BYTEAOID:
					data += "X\'";
					for (value = PQgetvalue(res, k, i) + 2; *value != '\0'; value++) {
								//skip beginning "\\x"
						data += toupper(*value);
					}
					data += '\'';
					break;
				case BOOLOID:
					data += BOOLALPHA_UPPERCASE(*PQgetvalue(res, k, i));
							//PostgreSQL prints BOOLEAN as t and f
					break;
				default:
					throw unsupported_exception("unsupported data type");
				}
			}
			data += ',';
		}
		data.back() = ';';
	}
	data.pop_back();
	LOG_CPP(" to \"" << data << '\"' << endl);
	LEN = data.length();
	fmsg.reset(new(LEN) formatted_message(LEN));
	fmsg->HD.put_as_byte(0b11111011);//ID,LEN,DST,SRC,CRC,CONF,AUTH
	THR(iter == addr_remote.cend(), message_exception("DST does not exist"));
	fmsg->ID = iter->second->out_ID++;
	memcpy_endian(&fmsg->LEN, &LEN, 2);
	memcpy_endian(&fmsg->DST, &DST, 8);
	memcpy_endian(&fmsg->SRC, &local_addr, 8);
	data.copy(reinterpret_cast<char *>(fmsg->PL), LEN);
	fmsg->HD.put_as_byte(header::reverse_byte(fmsg->HD.get_as_byte()));
	fmsg->CRC = givecrc32c(reinterpret_cast<BYTE *>(fmsg.get()) + 4, LEN + fields_MAX);
			//HD,ID,LEN,DST,SRC
	fmsg->HD.put_as_byte(header::reverse_byte(fmsg->HD.get_as_byte()));
	if (little_endian) {
		fmsg->LEN = LEN;
		fmsg->DST = DST;
		fmsg->SRC = local_addr;
	}
	LOG_CPP("sending " << *fmsg << endl);
	send_formatted_message(fmsg.release());
	return res;
}

void send_formatted_message(formatted_message *fmsg) {
	unique_ptr<raw_message> rmsg;
	int i;
	map<BYTE8, remote *>::iterator iter_DST_destination = addr_remote.find(fmsg->DST);
	multimap<protocol *, BYTE8>::iterator iter_iSRC;
	bool failed = true;
	my_time_point now = my_now();
	map<protocol *, map<BYTE8, my_time_point> *>::iterator iter_proto_iDST_TWR;
	map<BYTE8, my_time_point>::iterator iter_iDST_TWR;
	regex re("d=[0-9]{1,3}");
	istringstream iss;
	chrono::system_clock::rep dt;
	unique_ptr<formatted_message> copy, dummy_f(fmsg);
	map<string, string *>::const_iterator iter_table_user
			= table_user.find("t"s + BYTE8_to_c17charp(fmsg->SRC));
	configuration *c = username_configuration[iter_table_user != table_user.cend()
			&& iter_table_user->second != nullptr ? *iter_table_user->second : "root"];

	THR(iter_DST_destination == addr_remote.end(), message_exception("DST does not exist"));
	for (iter_proto_iDST_TWR = iter_DST_destination->second->proto_iSRC_TWR.begin();
			iter_proto_iDST_TWR != iter_DST_destination->second->proto_iSRC_TWR.end();
			iter_proto_iDST_TWR++) {
		for (iter_iDST_TWR = iter_proto_iDST_TWR->second->begin();
				iter_iDST_TWR != iter_proto_iDST_TWR->second->end(); iter_iDST_TWR++) {
			dt = chrono::duration_cast<chrono::seconds>(now - iter_iDST_TWR->second).count();
			if (dt > c->nsecs_src) {
				LOG_CPP("proto " << iter_proto_iDST_TWR->first << " imm_DST "
						<< BYTE8_to_c17charp(iter_iDST_TWR->first) << " expired by " << dt
						<< " seconds" << endl);
				iter_proto_iDST_TWR->second->erase(iter_iDST_TWR);
			} else {
				copy.reset(new(fmsg->LEN) formatted_message(*fmsg));
				rmsg.reset(new raw_message(new BYTE[fmsg->LEN + fields_MAX + 4]));
				rmsg->imm_addr = iter_iDST_TWR->first;
				rmsg->proto = iter_proto_iDST_TWR->first;
				rmsg->CCF = !c->trust_everyone && fmsg->HD.C
						&& rmsg->proto->can_secure_with_C(*rmsg);
				rmsg->ACF = !c->trust_everyone && fmsg->HD.A
						&& rmsg->proto->can_secure_with_A(*rmsg);
				dummy_f.reset(apply_rules(dummy_f.release(), rmsg.get(), true));
				if (dummy_f == nullptr) {
					continue;
				}
				if (regex_match(reinterpret_cast<char *>(fmsg->PL), re)) {
					iss.str(reinterpret_cast<char *>(fmsg->PL) + 2);
					iss >> i;
					iss.clear();
					if (i < 256) {
						fmsg->PL[0] = i;
						fmsg->LEN = 1;
					}
				}
				encode_message(*fmsg, *rmsg);
				rmsg->TWR = my_now();
				security_check_for_sending(*fmsg, *rmsg);
				LOG_CPP("sending " << *rmsg << endl);
				send_raw_message(rmsg.release());
				failed = false;
			}
		}
		if (iter_proto_iDST_TWR->second->empty()) {
			iter_DST_destination->second->proto_iSRC_TWR.erase(iter_proto_iDST_TWR);
		}
	}
	THR(failed, message_exception("imm_DST does not exist"));
}

#ifdef OFFLINE
void send_raw_message(raw_message *rmsg) {
	ofstream ofs("test.txt", ofs.out | ofs.ate | ofs.binary);
	unique_ptr<raw_message> dummy(rmsg);

	ofs.write(reinterpret_cast<char *>(rmsg->msg), rmsg->TML);
}
#else
void send_raw_message(raw_message *rmsg) {
	unique_ptr<raw_message> dummy(rmsg);

	THR(mq_send(rmsg->proto->get_my_mq(), reinterpret_cast<char *>(&rmsg), sizeof(rmsg), 0) < 0,
			system_exception("cannot send to queue"));
	dummy.release();
}
#endif /* OFFLINE */

void ins(string data, BYTE8 address) {
	string addr(BYTE8_to_c17charp(address));
	const char *type;
	vector<string> columns, types;
	PGresult *res = execcheckreturn("SELECT relname FROM pg_class WHERE relname "
			"LIKE \'t________________\' AND relname <> \'table_constraints\'");//"\\d" won't work
	vector<datatypes> dt;
	stringstream ss;
	vector<typedetails> td;
	typedetails old_td;
	int i = PQntuples(res) - 1, j;

	data += "##";
	format_insert(data, columns, types, dt, td);
	THR(!clean_insert(data), error_exception("error in INSERT"));
	for (addr = 't' + addr; i >= 0 && addr != PQgetvalue(res, i, 0); i--)
		;
	if (i < 0) {
		create_table(addr, columns, types);
	} else {
		PQclear(res);
		res = execcheckreturn("SELECT attname, format_type(atttypid, atttypmod) FROM pg_attribute "
				"INNER JOIN pg_class ON attrelid = oid WHERE attnum > 0 AND relname = \'" + addr
				+ '\'');//"\\d " + addr won't work
		for (i = columns.size() - 1; i >= 0; i--) {
			for (j = PQntuples(res) - 1; j >= 0; j--) {
				if ((columns[i].front() == '\"' ? columns[i].substr(1, columns[i].length() - 2)
						: columns[i]) == PQgetvalue(res, j, 0)) {
					type = PQgetvalue(res, j, 1);
					switch (dt[i]) {
					case datatypes::NUMERIC:
						THR(strncmp(type, "numeric(", 8) != 0, error_exception("changed type"));
						ss.str(type + 8);//strlen("numeric(") = 8
						(ss >> old_td.numeric.precision).ignore() >> old_td.numeric.scale;
						if (old_td.numeric.precision >= td[i].numeric.precision) {
							if (old_td.numeric.scale >= td[i].numeric.scale) {
								columns.erase(columns.begin() + i);
								types.erase(types.begin() + i);
							} else {
								columns[i].back() += 128;
								ss.str("");
								ss.clear();
								ss << old_td.numeric.precision << ',' << td[i].numeric.scale;
								types[i] = "numeric(" + ss.str() + ')';
							}
						} else {
							columns[i].back() += 128;
							ss.str("");
							ss.clear();
							ss << td[i].numeric.precision << ',' <<
									(old_td.numeric.scale >= td[i].numeric.scale ?
									old_td.numeric.scale : td[i].numeric.scale);
							types[i] = "numeric(" + ss.str() + ')';
						}
						break;
					case datatypes::TEXT:
#define DEFAULT_CHECK_INS(typestr) \
		do { \
			THR(strcmp(type, typestr) != 0, error_exception("changed type")); \
			columns.erase(columns.begin() + i); \
			types.erase(types.begin() + i); \
		} while (0)
						DEFAULT_CHECK_INS("text");
						break;
					case datatypes::BOOLEAN:
						DEFAULT_CHECK_INS("boolean");
						break;
					case datatypes::TIMESTAMP:
#define TIMESTAMP_OR_TIME_CHECK_INS(typestr, typelen) \
		do { \
			THR(strncmp(type, #typestr "(", typelen) != 0, error_exception("changed type")); \
			ss.str(type + typelen);/*strlen(#typestr "(") = typelen*/ \
			ss >> old_td.typestr.precision; \
			old_td.typestr.with_time_zone = type[typelen + 7] == ' '; \
			if (old_td.typestr.precision >= td[i].typestr.precision) { \
				if (old_td.typestr.with_time_zone || !td[i].typestr.with_time_zone) { \
					columns.erase(columns.begin() + i); \
					types.erase(columns.begin() + i); \
				} else { \
					columns[i].back() += 128; \
					ss.str(""); \
					ss.clear(); \
					ss << old_td.typestr.precision; \
					types[i] = #typestr "(" + ss.str() + ") with time zone"; \
				} \
			} else { \
				columns[i].back() += 128; \
				ss.str(""); \
				ss.clear(); \
				ss << td[i].typestr.precision; \
				types[i] = #typestr "(" + ss.str() + ((old_td.typestr.with_time_zone \
						|| !td[i].typestr.with_time_zone) ? ") with time zone" \
						: ") without time zone"); \
			} \
		} while (0)
						TIMESTAMP_OR_TIME_CHECK_INS(timestamp, 10);
						break;
					case datatypes::TIME:
						TIMESTAMP_OR_TIME_CHECK_INS(time, 5);
						break;
					case datatypes::DATE:
						DEFAULT_CHECK_INS("date");
						break;
					case datatypes::INTERVAL:
						THR(strncmp(type, "interval(", 9) != 0, error_exception("changed type"));
						ss.str(type + 9);//strlen("interval(") = 9
						ss >> old_td.interval.precision;
						if (old_td.interval.precision >= td[i].interval.precision) {
							columns.erase(columns.begin() + i);
							types.erase(types.begin() + i);
						} else {
							columns[i].back() += 128;
							ss.str("");
							ss.clear();
							ss << td[i].interval.precision;
							types[i] = "interval(" + ss.str() + ')';
						}
						break;
					default://datatypes::BYTEA
						DEFAULT_CHECK_INS("bytea");
					}
					break;
				}
			}
		}
		alter_table(addr, columns, types);
	}
	PQclear(res);
	PQclear(execcheckreturn("INSERT INTO " + addr + data));
}

/* this function is guaranteed to run on non-resource-constrained hardware */
void format_insert(string &data, vector<string> &columns, vector<string> &types,
		vector<datatypes> &dt, vector<typedetails> &td) {
	regex re(RE_STRING "|=|,|;|#");
	const int m[2] = { -1, 0 };
	int i, j;
	bool no_t = true;
	string temp("("), first, second;
	ostringstream oss;
	sti iter_begin(data.cbegin(), data.cend(), re, m), iter_end;

	LOG_CPP("formatting INSERT \"" << data);
	format_insert_header(iter_begin, iter_end, temp, columns, first, second, no_t);
	temp += ") VALUES(";
	dt.push_back(static_cast<datatypes>(columns.size()));
	format_insert_body(iter_begin, iter_end, temp, dt, td, first, second, no_t);
	data = temp + ')';
	for (i = 0, j = dt.size(); i < j; i++) {
		switch (dt[i]) {
		case datatypes::NUMERIC:
			oss << td[i].numeric.precision << ',' << td[i].numeric.scale;
			types.push_back("numeric(" + oss.str() + ')');
			break;
		case datatypes::TEXT:
			types.push_back("text");
			break;
		case datatypes::BOOLEAN:
			types.push_back("boolean");
			break;
		case datatypes::TIMESTAMP:
			oss << td[i].timestamp.precision;
			types.push_back("timestamp(" + oss.str() + (td[i].timestamp.with_time_zone
					? ") with time zone" : ") without time zone"));
			break;
		case datatypes::DATE:
			types.push_back("date");
			break;
		case datatypes::TIME:
			oss << td[i].time.precision;
			types.push_back("time(" + oss.str() + (td[i].time.with_time_zone
					? ") with time zone" : ") without time zone"));
			break;
		case datatypes::INTERVAL:
			oss << td[i].interval.precision;
			types.push_back("interval(" + oss.str() + ')');
			break;
		default://BYTEA
			types.push_back("bytea");
		}
		oss.str("");
	}
	LOG_CPP("\" to \"" << data << '\"' << endl);
}

void format_insert_header(sti &iter_begin, sti &iter_end, string &temp, vector<string> &columns,
		string &first, string &second, bool &no_t) {
	bool added = false, loop = true;
	string second_temp, str;
	int index = 2;
	ostringstream oss(oss.out | oss.ate);
	sti iter_temp;

	first = iter_begin++->str();
	second = iter_begin++->str();
	if (second.front() != '\"' && (first.empty() || !islower(first.front()))) {
		/* no header present */
		second_temp = second;
		iter_temp = iter_begin;
		columns.push_back("d1");
		temp += "d1";
		do {//final, empty iter + 1 == iter_end is not reached
			switch (second_temp.front()) {
			case ','://field
				oss.str("d");
				oss << index++;
				str = oss.str();
				columns.push_back(str);
				temp += ", " + str;
				break;
			case ';':
			case '#':
				loop = false;
				break;
			default://string
				second_temp = (++iter_temp)++->str();
			}
		} while (loop);
	} else {
		/* header is present */
		do {//final, empty iter_begin + 1 == iter_end is not reached
			if (first.empty()) {
				switch (second.front()) {
				case '\'':
					throw error_exception("\'\\\'\' in header");
				case '\"'://add double-quoted header field
					THR(added, error_exception("crowded header field"));
					columns.push_back(second);
					if (second == "\"t\"") {
						no_t = false;
					}
					added = true;
					temp += second;
					break;
				case '='://end of header
				case ','://end of header field
					THR(!added, error_exception("empty header field"));
					if (second.front() == '=') {//end of header
						loop = false;
					} else {//end of header field
						added = false;
						temp += ", ";
					}
					break;
				case ';':
					throw error_exception("\';\' in header");
				default://'#'
					throw error_exception("header ended too quickly");
				}
			} else {
				switch (second.front()) {
				case '\'':
					throw error_exception("\'\\\'\' in header");
				case '\"':
					THR(added, error_exception("crowded data field"));
					added = true;
					if (first.front() == 'U') {//add unicode header field
						if (!iter_begin->str().empty()) {
							columns.push_back("U&" + second + " UESCAPE \'"
									+ (++iter_begin)++->str() + '\'');
						} else {
							columns.push_back("U&" + second);
						}
					} else {
						throw error_exception("crowded header field");
					}
					break;
				case '='://end of header//add normal header field
				case ','://end of header field//add normal header field
					THR(added, error_exception("crowded header field"));
					columns.push_back(first);
					temp += first;
					if (first == "t") {
						no_t = false;
					}
					if (second.front() == '=') {//end of header
						loop = false;
					} else {//end of header field
						added = false;
						temp += ", ";
					}
					break;
				case ';':
					throw error_exception("\';\' in header");
				default://'#'
					throw error_exception("header ended too quickly");
				}
			}
			first = iter_begin++->str();
			second = iter_begin++->str();
		} while (loop);
		if (columns.size() == 1) {//partial header present
			second_temp = second;
			iter_temp = iter_begin;
			loop = true;
			do {//final, empty iter + 1 == iter_end is not reached
				switch (second_temp.front()) {
				case ','://field
					oss.str(columns.front());
					oss.seekp(-1, oss.end) << index++;
					str = oss.str();
					columns.push_back(str);
					temp += ", " + str;
					break;
				case ';':
				case '#':
					loop = false;
					break;
				default:
					second_temp = (++iter_temp)++->str();
				}
			} while (loop);
			if (columns.size() != 1) {
				temp.insert(columns.front().length(), 1, '1');
				columns.front() += '1';
				no_t = true;
			}
		}
	}
	if (no_t) {
		temp += ", t";
	}
}

//todo binary literals
//the number of columns is sent through a member of var "dt" (cast to type)
void format_insert_body(sti &iter_begin, sti &iter_end, string &temp, vector<datatypes> &dt,
		vector<typedetails> &td, string &first, string &second, bool &no_t) {
	int max_column = static_cast<int>(dt.back()) - 1, column = 0, period, e, expo, row = 0;
	bool added = false, loop = true;
	stringstream ss(ss.out | ss.ate);
	typedetails new_td;
	string uescape;

	dt.pop_back();
	do {//final, empty iter_begin + 1 == iter_end is not reached
		if (first.empty()) {
			switch (second.front()) {
			case '\''://add string data field
				THR(added, error_exception("crowded data field"));
				added = true;
				if (static_cast<int>(dt.size()) <= column) {
					dt.push_back(datatypes::TEXT);
					td.push_back(new_td);
				} else {
					THR(dt[column] != datatypes::TEXT,
							error_exception("changed type"));
				}
				temp += second;
				break;
			case '\"':
				throw error_exception("\'\\\"\' in data");
			case '=':
				throw error_exception("\'=\' in data");
			default://',' or ';' or '#'//end of data field or end of data row or end of data
				THR(!added, error_exception("empty data field"));
				if (second.front() == ',') {//end of data field
					THR(++column > max_column, error_exception("too many columns"));
					added = false;
					temp += ", ";
				} else {//end of data row or end of data
					THR(column < max_column, error_exception("too little columns"));
					if (no_t) {
						ss.str(", LOCALTIMESTAMP + INTERVAL \'0:0:0.0001\' * ");
						ss << row;
						temp += ss.str();
					}
					if (second.front() == ';') {//end of data row
						row++;
						column = 0;
						added = false;
						temp += "), (";
					} else {//end of data
						loop = false;
					}
				}
			}
		} else {
			switch (second.front()) {
			case '\''://add timestamp, interval, binary, or unicode data field
				THR(added, error_exception("crowded data field"));
				added = true;
				switch (first.front()) {
				case 'T'://add timestamp or time data field
#define TIMESTAMP_OR_TIME_CHECK_BODY(typestr, TYPESTR) \
		do { \
			period = second.rfind('.');/*reverse for speed*/ \
			new_td.typestr.precision = period == static_cast<int>(string::npos) ? 0 \
					: second.length() - period - 1; \
			period = second.rfind(' ');/*reverse for speed*/ \
			THR(period == static_cast<int>(string::npos), error_exception("error in " #typestr)); \
			new_td.typestr.with_time_zone \
					= second.find_first_of("+-", period) != string::npos; \
					/*only possible format is uint-uint-uint uint:uint:uint[.uint]{+|-}uint:uint*/ \
							/*by the SQL standard*/ \
			if (static_cast<int>(dt.size()) <= column) { \
				dt.push_back(datatypes::TYPESTR); \
				td.push_back(new_td); \
			} else { \
				THR(dt[column] != datatypes::TYPESTR, error_exception("changed type")); \
				if (td[column].typestr.precision < new_td.typestr.precision) { \
					td[column].typestr.precision = new_td.typestr.precision; \
				} \
				if (!td[column].typestr.with_time_zone && new_td.typestr.with_time_zone) \
						{ \
					td[column].typestr.with_time_zone = true; \
				} \
			} \
			temp += (td.back().typestr.with_time_zone ? #TYPESTR " WITH TIME ZONE " \
					: #TYPESTR " ") + second;/*"timezonedness" is not recognized by Postgres*/ \
                                             /*(all TYPESTR literals are treated WITHOUTtz)*/ \
                                             /*and needs to be set explicitly when WITHtz*/ \
		} while (0)
					THR(first.length() < 5, error_exception("malformed timestamp or time"));
					if (first[4] == 'S') {//add timestamp data field
						TIMESTAMP_OR_TIME_CHECK_BODY(timestamp, TIMESTAMP);
					} else {//add time data field
						TIMESTAMP_OR_TIME_CHECK_BODY(time, TIME);
					}
					break;
				case 'D'://add date data field
				case '\x9B'://DATE
#define DEFAULT_CHECK_BODY(type) \
		do { \
			if (static_cast<int>(dt.size()) <= column) { \
				dt.push_back(datatypes::type); \
				td.push_back(new_td); \
			} else { \
				THR(dt[column] != datatypes::type, error_exception("changed type")); \
			} \
		} while (0)
					DEFAULT_CHECK_BODY(DATE);
					temp += "DATE " + second;
					break;
				case '\xEF'://TIMESTAMP
					TIMESTAMP_OR_TIME_CHECK_BODY(timestamp, TIMESTAMP);
					break;
				case 'I'://add interval data field
				case '\xB5'://INTERVAL
					period = second.rfind('.');//reverse for speed
					new_td.timestamp.precision = period == static_cast<int>(string::npos) ? 0
							: second.length() - period - 1;
					//only possible format is [+|-]uint[-uint| uint[:uint[:uint[.uint]]]|
							//:uint[:uint[.uint]] by the SQL standard
					if (static_cast<int>(dt.size()) <= column) {
						dt.push_back(datatypes::INTERVAL);
						td.push_back(new_td);
					} else {
						THR(dt[column] != datatypes::INTERVAL, error_exception("changed type"));
						if (td[column].interval.precision < new_td.interval.precision) {
							td[column].interval.precision = new_td.interval.precision;
						}
					}
					temp += "INTERVAL " + second;
					break;
				case '\xEE'://TIME
					TIMESTAMP_OR_TIME_CHECK_BODY(time, TIME);
					break;
				case 'X'://add binary data field
					DEFAULT_CHECK_BODY(BYTEA);
					temp += "E\'\\\\x" + second.substr(1);
					break;
				case 'U'://add unicode data field
					if (iter_begin->str().empty()) {
						uescape.clear();
					} else {
						uescape = " UESCAPE " + (++iter_begin)++->str();
						THR(uescape.length() < 10 || uescape[9] != '\'',
								error_exception("error in uescape"));//following index exists
					}
					THR(first.length() < 2 || first[1] != '&', error_exception("error in unicode"));
					if (static_cast<int>(dt.size()) <= column) {
						dt.push_back(datatypes::TEXT);
						td.push_back(new_td);
					} else {
						THR(dt[column] != datatypes::TEXT, error_exception("changed type"));
					}
					temp += "U&" + second + uescape;
					break;
				default:
					throw error_exception("crowded data field");
				}
				break;
			case '\"':
				throw error_exception("\'\\\"\' in data");
			case '=':
				throw error_exception("\'=\' in data");
			default://',' or ';' or '#'
					//end of data field or end of data row or end of data
					//add boolean or number data field
				THR(added, error_exception("crowded data field"));
				if (!isdigit(first.front())) {//add boolean field
					if (first.front() == 'U') {
						first = "NULL";
					}//UNKNOWN literals are not recognized by Postgres
					 //(even though IS UNKNOWN predicate _is_ recognized)
					 //and need to be converted to NULL equivalent form
					DEFAULT_CHECK_BODY(BOOLEAN);
					temp += second;
				} else {//add number data field
					e = first.rfind('E');//deliberately not ignoring case
					period = first.rfind('.');
					if (e != static_cast<int>(string::npos)) {//E-number
						new_td.numeric.precision = e;
						new_td.numeric.scale = period == static_cast<int>(string::npos) ? 0
								: e - period - 1;
						ss.str(first.substr(e + 1));
						ss >> expo;
						ss.clear();
						if (expo > 0) {
							if (period == static_cast<int>(string::npos)) {
								new_td.numeric.precision += expo;
							} else if (new_td.numeric.scale > expo) {
								new_td.numeric.precision -= expo;
								new_td.numeric.scale -= expo;
							} else {
								new_td.numeric.precision += expo - new_td.numeric.scale + 1;
								new_td.numeric.scale = 0;
							}
						} else {
							new_td.numeric.precision -= expo;
							new_td.numeric.scale -= expo;
						}
					} else {//normal number
						new_td.numeric.precision = first.length();
						new_td.numeric.scale = period == static_cast<int>(string::npos) ? 0
								: new_td.numeric.precision - period - 1;
					}
					if (static_cast<int>(dt.size()) <= column) {
						dt.push_back(datatypes::NUMERIC);
						td.push_back(new_td);
					} else {
						THR(dt[column] != datatypes::NUMERIC, error_exception("changed type"));
						if (td[column].numeric.precision < new_td.numeric.precision) {
							td[column].numeric.precision = new_td.numeric.precision;
						}
						if (td[column].numeric.scale < new_td.numeric.scale) {
							td[column].numeric.scale = new_td.numeric.scale;
						}
					}
				}
				if (second.front() == ',') {//end of data field
					THR(++column > max_column, error_exception("too many columns"));
					added = false;
					temp += first + ", ";
				} else {//end of data row or end of data
					THR(column < max_column, error_exception("too little columns"));
					temp += first;
					if (no_t) {
						ss.str(", LOCALTIMESTAMP + INTERVAL \'0:0:0.0001\' * ");
						ss << row;
						temp += ss.str();
					}
					if (second.front() == ';') {//end of data row
						row++;
						column = 0;
						added = false;
						temp += "), (";
					} else {//end of data
						loop = false;
					}
				}
			}
		}
		first = iter_begin++->str();
		second = iter_begin++->str();
	} while (loop);
}

bool clean_insert(string data) {
	regex re_split(RE_STRING);
	sti iter_begin(data.cbegin(), data.cend(), re_split, -1), iter_end;

	do {
		if (iter_begin->str().rfind(';') != string::npos) {//reverse for speed
			return false;
		}
	} while (++iter_begin != iter_end);
	return true;
}

void create_table(string address, vector<string> &columns, vector<string> &types) {
	string coltyp("(");
	int i = -1, j = columns.size();

	while (++i < j) {
		if (columns[i] != "t") {
			coltyp += columns[i] + ' ' + types[i] + ", ";
		}
	}
	PQclear(execcheckreturn("CREATE TABLE " + address + coltyp
			+ "t TIMESTAMP(4) WITHOUT TIME ZONE, PRIMARY KEY(t))"));//strlen("4294967296") = 10
}

//if the final character of a member of var "columns" is inverted, the column needs altering
void alter_table(string address, vector<string> &columns, const vector<string> &types) {
	int i = -1, j = columns.size();

	while (++i < j) {
		if (columns[i].back() < 0) {
			columns[i].back() -= 128;
			PQclear(execcheckreturn("ALTER TABLE " + address + " ALTER COLUMN " + columns[i]
					+ " SET DATA TYPE " + types[i]));
		} else {
			PQclear(execcheckreturn("ALTER TABLE " + address + " ADD COLUMN " + columns[i]
					+ ' ' + types[i]));
		}
	}
}

BYTE8 EUI48_to_EUI64(BYTE8 EUI48) noexcept {
	return (EUI48 << 16 & 0xFFFFFF00'00000000) | 0x000000FF'FE000000 | (EUI48 & 0x00000'00FFFFFF);
}
